import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "CVortex",
  description: "Your career, in context.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
