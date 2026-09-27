import { reportBrowserError } from "./app/report-browser-error";

window.addEventListener("error", () => { void reportBrowserError("runtime"); });
window.addEventListener("unhandledrejection", () => { void reportBrowserError("rejection"); });
