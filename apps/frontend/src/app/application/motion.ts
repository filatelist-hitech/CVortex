import { useEffect, useMemo } from "react";

type Options = { fresh?: boolean; x?: number; y?: number; scale?: number; complete?: (() => void) | null };
type State = { element: HTMLElement; value: number; velocity: number; target: number; x: number; y: number; scale: number; time: number; frame: number; complete: (() => void) | null };

// Exact critical-damping solution from canonical motion.js. React owns visibility;
// this controller owns only temporary compositor properties and preserves velocity.
export function createWorkspaceMotion() {
  const states = new WeakMap<HTMLElement, State>(), active = new Set<State>();
  const omega = 2 * Math.PI / 0.30;
  function finish(state: State) {
    cancelAnimationFrame(state.frame); active.delete(state);
    state.value = state.target; state.velocity = 0;
    for (const name of ["will-change", "transform", "opacity"]) state.element.style.removeProperty(name);
    const complete = state.complete; state.complete = null; complete?.();
  }
  function paint(state: State) {
    const progress = Math.max(0, Math.min(1, state.value));
    state.element.style.opacity = String(progress);
    state.element.style.transform = `translate3d(${state.x * (1 - progress)}px, ${state.y * (1 - progress)}px, 0) scale(${1 - state.scale * (1 - progress)})`;
  }
  function tick(state: State, time: number) {
    const dt = Math.min((time - state.time) / 1000, 0.064); state.time = time;
    const offset = state.value - state.target, term = state.velocity + omega * offset, decay = Math.exp(-omega * dt);
    state.value = state.target + (offset + term * dt) * decay;
    state.velocity = (state.velocity - omega * term * dt) * decay;
    paint(state);
    if (Math.abs(state.value - state.target) < 0.002 && Math.abs(state.velocity) < 0.02) finish(state);
    else state.frame = requestAnimationFrame(next => tick(state, next));
  }
  function to(element: HTMLElement, target: number, { fresh = false, x = 0, y = 8, scale = 0, complete = null }: Options = {}) {
    let state = states.get(element);
    if (!state) { state = { element, value: 1, velocity: 0, target, x, y, scale, time: 0, frame: 0, complete: null }; states.set(element, state); }
    cancelAnimationFrame(state.frame);
    if (fresh) { state.value = 0; state.velocity = 0; }
    Object.assign(state, { target, x, y, scale, complete, time: performance.now() });
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) { finish(state); return; }
    element.style.willChange = "transform, opacity"; active.add(state); paint(state);
    state.frame = requestAnimationFrame(time => tick(state, time));
  }
  function stop(element: HTMLElement) {
    const state = states.get(element);
    if (state) { state.complete = null; state.target = 1; finish(state); }
  }
  return { to, stop, reduce: () => [...active].forEach(finish), dispose: () => [...active].forEach(state => stop(state.element)) };
}

export function useWorkspaceMotion() {
  const motion = useMemo(() => createWorkspaceMotion(), []);
  useEffect(() => {
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)");
    const change = () => { if (reduced.matches) motion.reduce(); };
    reduced.addEventListener("change", change);
    return () => { reduced.removeEventListener("change", change); motion.dispose(); };
  }, [motion]);
  return motion;
}
