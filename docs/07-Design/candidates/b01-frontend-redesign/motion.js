/* Candidate-only critically damped motion. No timers on the input/approval path. */
(() => {
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const states = new WeakMap();
  const active = new Set();
  const omega = 2 * Math.PI / 0.30;
  function paint(state) {
    const progress = Math.max(0, Math.min(1, state.value));
    state.element.style.opacity = String(progress);
    state.element.style.transform = `translate3d(${state.x * (1 - progress)}px, ${state.y * (1 - progress)}px, 0) scale(${1 - state.scale * (1 - progress)})`;
  }
  function finish(state) {
    cancelAnimationFrame(state.frame);
    active.delete(state);
    state.value = state.target; state.velocity = 0;
    state.element.style.removeProperty('will-change');
    state.element.style.removeProperty('transform');
    state.element.style.removeProperty('opacity');
    const complete = state.complete; state.complete = null;
    if (complete) complete();
  }
  function tick(state, time) {
    const dt = Math.min((time - state.time) / 1000, 0.064);
    state.time = time;
    // Exact solution for critical damping; retarget keeps the current velocity.
    const offset = state.value - state.target;
    const term = state.velocity + omega * offset;
    const decay = Math.exp(-omega * dt);
    state.value = state.target + (offset + term * dt) * decay;
    state.velocity = (state.velocity - omega * term * dt) * decay;
    paint(state);
    if (Math.abs(state.value - state.target) < 0.002 && Math.abs(state.velocity) < 0.02) finish(state);
    else state.frame = requestAnimationFrame(next => tick(state, next));
  }
  function to(element, target, { fresh = false, x = 0, y = 8, scale = 0, complete = null } = {}) {
    let state = states.get(element);
    if (!state) { state = { element, value: 1, velocity: 0 }; states.set(element, state); }
    cancelAnimationFrame(state.frame);
    if (fresh) { state.value = 0; state.velocity = 0; }
    Object.assign(state, { target, x, y, scale, complete, time: performance.now() });
    if (reduced.matches) { finish(state); return; }
    element.style.willChange = 'transform, opacity';
    active.add(state); paint(state);
    state.frame = requestAnimationFrame(time => tick(state, time));
  }
  function stop(element) {
    const state = states.get(element);
    if (state) { state.complete = null; state.target = 1; finish(state); }
  }
  reduced.addEventListener('change', () => { if (reduced.matches) [...active].forEach(finish); });
  window.CVOrbitMotion = { to, stop };
})();
