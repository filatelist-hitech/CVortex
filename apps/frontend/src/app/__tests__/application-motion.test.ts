import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { createWorkspaceMotion } from "../application/motion";

let clock = 0, sequence = 0, reduced = false;
const frames = new Map<number, FrameRequestCallback>();
beforeEach(() => {
  clock = 0; sequence = 0; reduced = false; frames.clear();
  vi.spyOn(performance, "now").mockImplementation(() => clock);
  vi.stubGlobal("requestAnimationFrame", (callback: FrameRequestCallback) => { frames.set(++sequence, callback); return sequence; });
  vi.stubGlobal("cancelAnimationFrame", (id: number) => frames.delete(id));
  vi.stubGlobal("matchMedia", () => ({ matches: reduced }));
});
afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); });
function frame() { clock += 16; const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback(clock)); }
function settle() { for (let i = 0; i < 100 && frames.size; i++) frame(); expect(frames.size).toBe(0); }

it("redirects closing from the current presentation without firing the stale close", () => {
  const motion = createWorkspaceMotion(), element = document.createElement("div"), close = vi.fn();
  motion.to(element, 1, { fresh: true, y: -8, scale: .01 }); settle();
  motion.to(element, 0, { y: -8, scale: .01, complete: close }); frame(); frame();
  const opacity = element.style.opacity, transform = element.style.transform;
  motion.to(element, 1, { y: -8, scale: .01 });
  expect(element.style.opacity).toBe(opacity); expect(element.style.transform).toBe(transform);
  settle(); expect(close).not.toHaveBeenCalled(); expect(element.style.transform).toBe("");
  motion.to(element, 0, { complete: close }); settle(); expect(close).toHaveBeenCalledOnce();
});

it("reduced motion completes immediately and an in-flight preference change settles", () => {
  const motion = createWorkspaceMotion(), element = document.createElement("div"), close = vi.fn();
  reduced = true; motion.to(element, 0, { complete: close });
  expect(close).toHaveBeenCalledOnce(); expect(frames.size).toBe(0); expect(element.style.transform).toBe("");
  reduced = false; motion.to(element, 1, { fresh: true }); frame(); reduced = true; motion.reduce();
  expect(frames.size).toBe(0); expect(element.style.opacity).toBe("");
});

it("unmount cancels animation and pending completion", () => {
  const motion = createWorkspaceMotion(), element = document.createElement("div"), close = vi.fn();
  motion.to(element, 0, { complete: close }); frame(); motion.dispose();
  expect(frames.size).toBe(0); expect(close).not.toHaveBeenCalled(); expect(element.style.willChange).toBe("");
});
