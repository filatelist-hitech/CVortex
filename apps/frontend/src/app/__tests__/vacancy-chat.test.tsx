import { act, cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import VacancyChat from "../vacancy-chat";
const mocks = vi.hoisted(() => ({ api: vi.fn() }));
vi.mock("../access-shell", () => ({ api: mocks.api }));
vi.mock("../chatgpt-connection", () => ({ default: ({ onSelect }: { onSelect: (id: string, model: string) => void }) => <button onClick={() => onSelect("connection", "entitled-model")}>Select connected plan</button> }));
const analysis = { requirements: [{ dimension: "TECHNICAL", importance: "MANDATORY", label: "<script>bad</script>", source_excerpt: "API testing" }], matches: [], gaps: ["No Kafka fact"], risks: [], questions: [], recommendations: [] };
let messages: { id: string; role: string; status: string; content: string; error_code: null }[] = [];
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); mocks.api.mockReset(); messages = []; });
function setup() {
  mocks.api.mockImplementation(async (path: string, options?: RequestInit) => {
    if (options?.method === "POST") return { data: { id: "draft", status: "DRAFT" } };
    if (path.endsWith("/analysis-drafts")) return { data: [] };
    return { data: { id: "thread", status: "COMPLETED", messages, model: null, connection_id: null } };
  });
  render(<VacancyChat vacancyId="vacancy" />);
}
describe("vacancy chat", () => {
  it("requires a selected connection and keeps persisted model output inert", async () => {
    messages = [{ id: "assistant", role: "assistant", status: "COMPLETED", content: JSON.stringify(analysis), error_code: null }];
    setup();
    expect(screen.getByRole("button", { name: "Analyze vacancy" })).toBeDisabled();
    expect(await screen.findByText("No Kafka fact")).toBeInTheDocument();
    expect(document.querySelector("script")).toBeNull();
    fireEvent.click(screen.getByRole("button", { name: "Save analysis" }));
    await waitFor(() => expect(mocks.api).toHaveBeenCalledWith("/api/v1/vacancies/vacancy/analysis-drafts", expect.objectContaining({ method: "POST", body: JSON.stringify({ message_id: "assistant", client_request_id: "message_assistant" }) })));
  });
  it("streams deltas and reloads saved messages only after successful completion", async () => {
    let controller: ReadableStreamDefaultController<Uint8Array>;
    const stream = new ReadableStream<Uint8Array>({ start(value) { controller = value; } });
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, body: stream }); vi.stubGlobal("fetch", fetchMock);
    setup(); await waitFor(() => expect(mocks.api).toHaveBeenCalledTimes(2));
    fireEvent.click(screen.getByRole("button", { name: "Select connected plan" }));
    fireEvent.click(screen.getByRole("button", { name: "Analyze vacancy" }));
    await act(async () => { controller.enqueue(new TextEncoder().encode('data: {"type":"delta","text":"partial analysis"}\n\n')); });
    expect(screen.getByText("Streaming")).toBeInTheDocument(); expect(screen.getByText("partial analysis")).toBeInTheDocument();
    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toMatchObject({ connection_id: "connection", model: "entitled-model", analyze: true });
    messages = [{ id: "assistant", role: "assistant", status: "COMPLETED", content: JSON.stringify(analysis), error_code: null }];
    await act(async () => { controller.enqueue(new TextEncoder().encode('data: {"type":"completed"}\n\n')); controller.close(); });
    expect(await screen.findByRole("button", { name: "Save analysis" })).toBeEnabled(); expect(screen.queryByText("Streaming")).not.toBeInTheDocument();
  });
  it("reports an interrupted stream and does not offer saving partial output", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, body: new ReadableStream({ start(controller) { controller.close(); } }) }));
    setup(); await waitFor(() => expect(mocks.api).toHaveBeenCalledTimes(2));
    fireEvent.click(screen.getByRole("button", { name: "Select connected plan" })); fireEvent.click(screen.getByRole("button", { name: "Analyze vacancy" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Streaming connection lost");
    expect(screen.queryByRole("button", { name: "Save analysis" })).not.toBeInTheDocument();
  });
  it("shows actionable usage-limit errors without an API-key fallback", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false, json: async () => ({ error: { code: "subscription_sharing_usage_limit_exceeded" } }) }));
    setup(); await waitFor(() => expect(mocks.api).toHaveBeenCalledTimes(2));
    fireEvent.click(screen.getByRole("button", { name: "Select connected plan" })); fireEvent.click(screen.getByRole("button", { name: "Analyze vacancy" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Usage limit reached");
    expect(screen.queryByText(/API key/)).not.toBeInTheDocument();
  });
});
