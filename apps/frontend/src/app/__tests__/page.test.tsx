import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import Home from "../page";

vi.mock("next/navigation", () => ({ useRouter: () => ({ replace: vi.fn() }) }));

describe("access shell", () => {
  it("renders the approved brand and sign-in form", () => {
    render(<Home />);

    expect(screen.getByRole("heading", { name: "CVortex" })).toBeInTheDocument();
    expect(screen.getByText("Your career, in context.")).toBeInTheDocument();
    expect(screen.getAllByRole("button", { name: "Sign in" })).toHaveLength(2);
    expect(screen.getByLabelText("Email")).toBeInTheDocument();
  });
});
