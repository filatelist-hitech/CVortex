import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import Home from "../page";

describe("technical shell", () => {
  it("renders the approved brand and runnable-core status", () => {
    render(<Home />);

    expect(screen.getByRole("heading", { name: "CVortex" })).toBeInTheDocument();
    expect(screen.getByText("Your career, in context.")).toBeInTheDocument();
    expect(screen.getByText("Technical baseline is running.")).toBeInTheDocument();
  });
});
