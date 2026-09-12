import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
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

    render(<RegisterPage />);

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
});
