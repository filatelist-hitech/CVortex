"use client";

import { useEffect, useState } from "react";
import { reportBrowserError } from "./report-browser-error";

export default function GlobalError({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const [reference, setReference] = useState<string | null>(null);
  useEffect(() => { void reportBrowserError("render", "global-root").then(setReference); }, []);
  return <html lang="en"><body><main className="shell"><section className="status-card" role="alert"><h1>Application unavailable</h1><p>Reload or try again. Error: FRONTEND_RUNTIME_ERROR</p>{(reference || error.digest) && <p>Reference: {reference || error.digest}</p>}<div className="actions"><button type="button" onClick={reset}>Retry</button><button type="button" className="secondary" onClick={() => window.location.reload()}>Reload</button></div></section></main></body></html>;
}
