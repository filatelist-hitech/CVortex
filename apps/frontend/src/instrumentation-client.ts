import { reportBrowserError } from "./app/report-browser-error";

window.addEventListener("error", (event: ErrorEvent) => { void reportBrowserError("runtime", "browser", event.error); });
window.addEventListener("unhandledrejection", (event: PromiseRejectionEvent) => { void reportBrowserError("rejection", "browser", event.reason); });
