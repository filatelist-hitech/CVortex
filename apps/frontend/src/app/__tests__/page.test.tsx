import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { StrictMode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import Home from "../page";
import RegisterPage from "../register/page";

vi.mock("next/navigation", () => ({ useRouter: () => ({ replace: vi.fn() }) }));

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  window.history.replaceState(null, "", "/");
});

describe("access shell", () => {
  it("renders the approved brand and sign-in form", () => {
    render(<Home />);

    expect(screen.getByRole("heading", { name: "CVortex" })).toBeInTheDocument();
    expect(screen.getByText("Your career, in context.")).toBeInTheDocument();
    expect(screen.getAllByRole("button", { name: "Sign in" })).toHaveLength(2);
    expect(screen.getByLabelText("Email")).toBeInTheDocument();
  });

  it("consumes a registration fragment, removes it from history, and submits it only in the registration body", async () => {
    window.history.replaceState(null, "", "/register#token=fragment-secret");
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/me") return new Response(null, { status: 401 });
      if (path === "/sanctum/csrf-cookie") return new Response(null, { status: 204 });
      if (path === "/api/v1/auth/register") return Response.json({ data: { id: "01JTEST", email: "invitee@example.test", role: "user", status: "ACTIVE" } }, { status: 201 });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<StrictMode><RegisterPage /></StrictMode>);

    await waitFor(() => expect(window.location.pathname).toBe("/register"));
    expect(window.location.hash).toBe("");
    expect(window.location.search).toBe("");
    expect(window.location.toString()).not.toContain("fragment-secret");
    expect(screen.getByRole("button", { name: "Create account" })).toBeEnabled();

    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "invitee@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "a very long safe passphrase" } });
    fireEvent.change(screen.getByLabelText("Confirm password"), { target: { value: "a very long safe passphrase" } });
    fireEvent.click(screen.getByRole("button", { name: "Create account" }));

    await waitFor(() => expect(screen.getByText("invitee@example.test")).toBeInTheDocument());
    const registrationRequest = fetchMock.mock.calls.find(([input]) => String(input) === "/api/v1/auth/register");
    expect(registrationRequest).toBeDefined();
    expect(JSON.parse(String(registrationRequest?.[1]?.body))).toMatchObject({ invitation_token: "fragment-secret" });
    expect(window.location.toString()).not.toContain("fragment-secret");
    expect(new URL(window.location.href).searchParams.toString()).not.toContain("fragment-secret");

  });

  it("renders pending evidence distinctly and sends explicit human review actions", async () => {
    const overview = {
      facts: [{ id: "fact-1", fact_type: "skill", assertion_original: "Familiar with Laravel.", assertion_approved: null, source_excerpt: "Familiar with Laravel.", provenance_type: "paste_extraction", status: "PENDING" }],
      claims: [],
      sources: [{ id: "source-1", extraction_status: "COMPLETED", error_code: null }],
    };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "career@example.test", role: "user", status: "ACTIVE" } });
      if (path === "/api/v1/career") return Response.json({ data: overview });
      if (path === "/api/v1/career/facts/fact-1/review" && options?.method === "PATCH") return Response.json({ data: { ...overview.facts[0], status: "CONFIRMED" } });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);

    expect(await screen.findByText("Pending confirmation")).toBeInTheDocument();
    expect(screen.getByText("Evidence available")).toBeInTheDocument();
    expect(screen.getByText("Claims appear only after explicit confirmation.")).toBeInTheDocument();
    expect(screen.queryByText("Confirmed", { selector: ".confirmed-badge" })).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Edit before confirming"), { target: { value: "Human-approved Laravel familiarity." } });
    fireEvent.click(screen.getByRole("button", { name: "Edit and Confirm" }));

    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith("/api/v1/career/facts/fact-1/review", expect.objectContaining({ method: "PATCH" })));
    const request = fetchMock.mock.calls.find(([input]) => String(input).includes("fact-1/review"));
    expect(JSON.parse(String(request?.[1]?.body))).toEqual({ action: "edit_confirm", assertion: "Human-approved Laravel familiarity." });
  });

  it("keeps manual entry available when extraction reports a provider error", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "career@example.test", role: "user", status: "ACTIVE" } });
      if (path === "/api/v1/career") return Response.json({ data: { facts: [], claims: [], sources: [] } });
      if (path === "/api/v1/career/extractions") return Response.json({ message: "Career extraction is temporarily unavailable. Manual fact entry is still available." }, { status: 503 });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);
    await screen.findByText("No confirmed facts yet.");
    fireEvent.change(screen.getByLabelText("Career or resume text"), { target: { value: "Synthetic career text." } });
    fireEvent.click(screen.getByRole("button", { name: "Extract pending facts" }));

    expect(await screen.findByText("Career extraction is temporarily unavailable. Manual fact entry is still available.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Add confirmed fact" })).toBeEnabled();
  });
});
