import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { MarginaliaCard, MarginaliaColumn, TruthStamp, type MarginaliaFact } from "../application/marginalia";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const mockConfirmedFact: MarginaliaFact = {
  id: "fact-42",
  statement: "Снизил latency транзакций на 40% за счёт шардирования PostgreSQL",
  status: "CONFIRMED",
  company: "FinTech Corp",
  year: 2023,
  linkedReqId: "req-1",
  sourceExcerpt: "Проектирование высоконагруженных API и кэширование",
};

const mockPendingFact: MarginaliaFact = {
  id: "fact-88",
  statement: "Разработка микросервисов на Go",
  status: "PENDING",
  company: "CloudTech",
  year: 2024,
  linkedReqId: "req-2",
};

describe("MarginaliaCard", () => {
  it("renders confirmed career fact card with truth stamp and meta", () => {
    const handleHover = vi.fn();
    const handleClick = vi.fn();

    render(
      <MarginaliaCard
        fact={mockConfirmedFact}
        isHighlighted={false}
        onHover={handleHover}
        onClick={handleClick}
      />
    );

    expect(screen.getByText(/\[Факт #42\]/)).toBeInTheDocument();
    expect(screen.getByText("[Подтверждено]")).toBeInTheDocument();
    expect(screen.getByText("FinTech Corp (2023)")).toBeInTheDocument();
    expect(screen.getByText(/Снизил latency транзакций на 40%/)).toBeInTheDocument();
    expect(screen.getByText(/Проектирование высоконагруженных API/)).toBeInTheDocument();

    const card = screen.getByRole("note", { name: /Маргиналия: факт fact-42/ });
    expect(card).toHaveClass("confirmed");
    expect(card).not.toHaveClass("is-highlighted");

    fireEvent.mouseEnter(card);
    expect(handleHover).toHaveBeenCalledWith(true);

    fireEvent.mouseLeave(card);
    expect(handleHover).toHaveBeenCalledWith(false);

    fireEvent.click(card);
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it("renders pending career fact card with pending stamp and highlight class", () => {
    render(
      <MarginaliaCard
        fact={mockPendingFact}
        isHighlighted={true}
      />
    );

    expect(screen.getByText("[На проверке]")).toBeInTheDocument();
    expect(screen.getByText("CloudTech (2024)")).toBeInTheDocument();
    expect(screen.getByText(/Разработка микросервисов на Go/)).toBeInTheDocument();

    const card = screen.getByRole("note", { name: /Маргиналия: факт fact-88/ });
    expect(card).toHaveClass("pending");
    expect(card).toHaveClass("is-highlighted");
  });
});

describe("MarginaliaColumn", () => {
  it("renders empty state placeholder when no facts are available", () => {
    render(<MarginaliaColumn facts={[]} />);

    expect(
      screen.getByText(/Маргиналии фактов карьеры появятся здесь после подтверждения данных/)
    ).toBeInTheDocument();
  });

  it("renders column with header count and handles hover and selection callbacks", () => {
    const facts = [mockConfirmedFact, mockPendingFact];
    const handleHoverFact = vi.fn();
    const handleSelectFact = vi.fn();

    render(
      <MarginaliaColumn
        facts={facts}
        activeFactId="fact-42"
        onHoverFact={handleHoverFact}
        onSelectFact={handleSelectFact}
      />
    );

    expect(screen.getByText("МАРГИНАЛИИ ПРАВДЫ")).toBeInTheDocument();
    expect(screen.getByText("2")).toBeInTheDocument();

    const confirmedCard = screen.getByRole("note", { name: /Маргиналия: факт fact-42/ });
    expect(confirmedCard).toHaveClass("is-highlighted");

    const pendingCard = screen.getByRole("note", { name: /Маргиналия: факт fact-88/ });
    expect(pendingCard).not.toHaveClass("is-highlighted");

    fireEvent.mouseEnter(pendingCard);
    expect(handleHoverFact).toHaveBeenCalledWith("fact-88");

    fireEvent.mouseLeave(pendingCard);
    expect(handleHoverFact).toHaveBeenCalledWith(null);

    fireEvent.click(confirmedCard);
    expect(handleSelectFact).toHaveBeenCalledWith("fact-42");
  });

  it("highlights fact when activeReqId matches linkedReqId", () => {
    render(
      <MarginaliaColumn
        facts={[mockConfirmedFact, mockPendingFact]}
        activeReqId="req-2"
      />
    );

    const pendingCard = screen.getByRole("note", { name: /Маргиналия: факт fact-88/ });
    expect(pendingCard).toHaveClass("is-highlighted");
  });
});

describe("TruthStamp", () => {
  it("renders verified truth stamp with verified styles and fact ID", () => {
    render(<TruthStamp factId="fact-42" status="CONFIRMED" />);
    const stamp = screen.getByText(/\[ VERIFIED FACT · #42 \]/);
    expect(stamp).toBeInTheDocument();
    expect(stamp.parentElement).toHaveClass("truth-stamp-verified");
  });

  it("renders pending review truth stamp with pending styles", () => {
    render(<TruthStamp factId="cf-99" status="PENDING" />);
    const stamp = screen.getByText(/\[ PENDING REVIEW · #99 \]/);
    expect(stamp).toBeInTheDocument();
    expect(stamp.parentElement).toHaveClass("truth-stamp-pending");
  });
});
