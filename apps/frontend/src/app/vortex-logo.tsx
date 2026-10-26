export function VortexSwissLogo({ size = 22 }: { size?: number }) {
  return (
    <span className="vortex-swiss-logo" aria-hidden="true">
      <svg width={size} height={size} viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M13 2.5C18.799 2.5 23.5 7.20101 23.5 13C23.5 15.65 22.45 18.05 20.75 19.85" stroke="#FFFFFF" strokeWidth="2.3" strokeLinecap="round" />
        <path d="M13 23.5C7.20101 23.5 2.5 18.799 2.5 13C2.5 10.35 3.55 7.95 5.25 6.15" stroke="var(--cv-accent-primary, var(--accent-primary, #FF5533))" strokeWidth="2.3" strokeLinecap="round" />
        <circle cx="13" cy="13" r="2.3" fill="var(--cv-accent-primary, var(--accent-primary, #FF5533))" />
      </svg>
    </span>
  );
}
