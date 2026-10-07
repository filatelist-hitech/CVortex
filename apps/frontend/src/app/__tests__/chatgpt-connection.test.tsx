import { act, cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import ChatGptConnectionPanel from "../chatgpt-connection";

const mocks = vi.hoisted(() => ({ api: vi.fn() }));
vi.mock("../access-shell", () => ({ api: mocks.api }));
afterEach(() => { cleanup(); vi.restoreAllMocks(); mocks.api.mockReset(); vi.unstubAllGlobals(); });

describe("ChatGPT plan capability check", () => {
  it("loads disconnected state only when opened", async () => {
    mocks.api.mockResolvedValue({ enabled: true, data: [] });
    render(<ChatGptConnectionPanel />);
    expect(mocks.api).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole("button", { name: "ChatGPT plan connection" }));
    expect(await screen.findByText("Not connected")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Continue with ChatGPT" })).toBeEnabled();
    expect(screen.queryByText(/Using ChatGPT plan/)).not.toBeInTheDocument();
  });

  it("shows the connected plan and dynamic model catalog", async () => {
    mocks.api.mockResolvedValueOnce({ enabled: true, data: [{ id: "connection", client_id: "oaiapp_test", status: "CONNECTED" }] })
      .mockResolvedValueOnce({ data: [{ slug: "account-model", display_name: "Account model" }] });
    render(<ChatGptConnectionPanel />);
    fireEvent.click(screen.getByRole("button", { name: "ChatGPT plan connection" }));
    expect(await screen.findByRole("option", { name: "Account model" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Manage usage" })).toHaveAttribute("href", "https://chatgpt.com/settings/usage");
    expect(screen.getByRole("button", { name: "Verify connection" })).toBeEnabled();
  });

  it("renders generated output as text and requires terminal completion", async () => {
    mocks.api.mockResolvedValueOnce({ enabled: true, data: [{ id: "connection", client_id: "oaiapp_test", status: "CONNECTED" }] })
      .mockResolvedValueOnce({ data: [{ slug: "model", display_name: "Model" }] });
    let controller: ReadableStreamDefaultController<Uint8Array>;
    const stream = new ReadableStream<Uint8Array>({ start(value) { controller = value; } });
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, body: stream }));
    render(<ChatGptConnectionPanel />);
    fireEvent.click(screen.getByRole("button", { name: "ChatGPT plan connection" }));
    await screen.findByRole("option", { name: "Model" });
    fireEvent.click(screen.getByRole("button", { name: "Verify connection" }));
    await act(async () => { controller.enqueue(new TextEncoder().encode('data: {"type":"delta","text":"<script>alert(1)</script>"}\n\n')); });
    expect(screen.getByText("Streaming")).toBeInTheDocument();
    expect(screen.getByText("<script>alert(1)</script>")).toBeInTheDocument();
    expect(document.querySelector("script")).toBeNull();
    await act(async () => { controller.close(); });
    expect(await screen.findByText("Interrupted")).toBeInTheDocument();
  });

  it("reports completed only after the terminal event", async () => {
    mocks.api.mockResolvedValueOnce({ enabled: true, data: [{ id: "connection", client_id: "oaiapp_test", status: "CONNECTED" }] })
      .mockResolvedValueOnce({ data: [{ slug: "model", display_name: "Model" }] });
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, body: new ReadableStream({ start(controller) {
      controller.enqueue(new TextEncoder().encode('data: {"type":"completed"}\n\n')); controller.close();
    } }) }));
    render(<ChatGptConnectionPanel />);
    fireEvent.click(screen.getByRole("button", { name: "ChatGPT plan connection" }));
    await screen.findByRole("option", { name: "Model" });
    fireEvent.click(screen.getByRole("button", { name: "Verify connection" }));
    expect(await screen.findByText("Completed")).toBeInTheDocument();
  });
});
