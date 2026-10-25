import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import AiProviderSettings from "../ai-provider-settings";

const mocks = vi.hoisted(() => ({ api: vi.fn() }));
vi.mock("../access-shell", () => ({ api: mocks.api }));

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  mocks.api.mockReset();
});

const defaultSettingsResponse = {
  data: {
    default_provider: "gemini",
    gemini: {
      has_key: true,
      masked_key: "AIzaSy...abcd",
      default_model: "gemini-1.5-flash",
      available_models: ["gemini-1.5-flash", "gemini-1.5-pro", "gemini-2.0-flash"],
      env_key_configured: true,
    },
    openai: {
      has_key: false,
      masked_key: null,
      default_model: "gpt-4o-mini",
      available_models: ["gpt-4o-mini", "gpt-4o"],
      env_key_configured: false,
    },
  },
};

describe("AiProviderSettings", () => {
  it("renders loaded settings with Gemini as default active provider", async () => {
    mocks.api.mockResolvedValueOnce(defaultSettingsResponse);

    render(<AiProviderSettings />);

    expect(screen.getByText("Загрузка конфигурации AI-провайдеров…")).toBeInTheDocument();

    expect(await screen.findByRole("heading", { name: "Настройки AI-провайдеров" })).toBeInTheDocument();
    expect(screen.getByRole("radio", { name: /Google Gemini/ })).toBeChecked();
    expect(screen.getByRole("radio", { name: /OpenAI/ })).not.toBeChecked();

    // Check Gemini details
    expect(screen.getByText("AIzaSy...abcd")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Проверить подключение Gemini" })).toBeInTheDocument();

    // Check OpenAI details
    expect(screen.getByRole("button", { name: "Проверить подключение OpenAI" })).toBeInTheDocument();
  });

  it("switches default provider between Gemini and OpenAI", async () => {
    mocks.api.mockResolvedValueOnce(defaultSettingsResponse);

    render(<AiProviderSettings />);
    await screen.findByRole("heading", { name: "Настройки AI-провайдеров" });

    const openaiRadio = screen.getByRole("radio", { name: /OpenAI/ });
    fireEvent.click(openaiRadio);

    expect(openaiRadio).toBeChecked();
    expect(screen.getByRole("radio", { name: /Google Gemini/ })).not.toBeChecked();
  });

  it("tests Gemini connection successfully and displays latency and model", async () => {
    mocks.api
      .mockResolvedValueOnce(defaultSettingsResponse)
      .mockResolvedValueOnce({
        status: "healthy",
        provider: "gemini",
        model: "gemini-1.5-flash",
        latency_ms: 185,
        message: "Gemini connection test successful.",
      });

    render(<AiProviderSettings />);
    await screen.findByRole("heading", { name: "Настройки AI-провайдеров" });

    const testGeminiBtn = screen.getByRole("button", { name: "Проверить подключение Gemini" });
    fireEvent.click(testGeminiBtn);

    expect(screen.getByText("Проверка подключения…")).toBeInTheDocument();

    expect(await screen.findByText("✓ Успешно")).toBeInTheDocument();
    expect(screen.getByText(/Gemini connection test successful/)).toBeInTheDocument();
    expect(screen.getByText(/185 мс · gemini-1.5-flash/)).toBeInTheDocument();

    expect(mocks.api).toHaveBeenCalledWith("/api/v1/ai/test-connection", {
      method: "POST",
      body: JSON.stringify({
        provider: "gemini",
        model: "gemini-1.5-flash",
      }),
    });
  });

  it("handles Gemini connection failure gracefully", async () => {
    mocks.api
      .mockResolvedValueOnce(defaultSettingsResponse)
      .mockRejectedValueOnce(new Error("API_KEY_INVALID: Invalid Gemini API key."));

    render(<AiProviderSettings />);
    await screen.findByRole("heading", { name: "Настройки AI-провайдеров" });

    const testGeminiBtn = screen.getByRole("button", { name: "Проверить подключение Gemini" });
    fireEvent.click(testGeminiBtn);

    expect(await screen.findByText("✗ Ошибка")).toBeInTheDocument();
    expect(screen.getByText(/API_KEY_INVALID: Invalid Gemini API key/)).toBeInTheDocument();
  });

  it("saves updated settings with PATCH request", async () => {
    mocks.api
      .mockResolvedValueOnce(defaultSettingsResponse)
      .mockResolvedValueOnce({
        data: {
          ...defaultSettingsResponse.data,
          default_provider: "gemini",
          gemini: {
            ...defaultSettingsResponse.data.gemini,
            default_model: "gemini-1.5-pro",
          },
        },
      });

    render(<AiProviderSettings />);
    await screen.findByRole("heading", { name: "Настройки AI-провайдеров" });

    // Change model
    const geminiSelect = screen.getByRole("combobox", { name: "Модель Gemini по умолчанию" });
    fireEvent.change(geminiSelect, { target: { value: "gemini-1.5-pro" } });

    // Submit form
    const saveBtn = screen.getByRole("button", { name: "Сохранить настройки" });
    fireEvent.click(saveBtn);

    expect(await screen.findByText("Настройки AI-провайдеров успешно сохранены.")).toBeInTheDocument();

    expect(mocks.api).toHaveBeenCalledWith("/api/v1/ai/settings", {
      method: "PATCH",
      body: JSON.stringify({
        default_provider: "gemini",
        gemini_model: "gemini-1.5-pro",
        openai_model: "gpt-4o-mini",
      }),
    });
  });

  it("allows removing stored BYOK key", async () => {
    mocks.api
      .mockResolvedValueOnce(defaultSettingsResponse)
      .mockResolvedValueOnce({
        data: {
          ...defaultSettingsResponse.data,
          gemini: {
            ...defaultSettingsResponse.data.gemini,
            has_key: false,
            masked_key: null,
          },
        },
      });

    render(<AiProviderSettings />);
    await screen.findByRole("heading", { name: "Настройки AI-провайдеров" });

    // Click remove key
    const removeBtn = screen.getByRole("button", { name: "Удалить ключ" });
    fireEvent.click(removeBtn);

    expect(screen.getByText(/Ключ будет удален после сохранения настроек/)).toBeInTheDocument();

    // Save
    const saveBtn = screen.getByRole("button", { name: "Сохранить настройки" });
    fireEvent.click(saveBtn);

    expect(mocks.api).toHaveBeenCalledWith("/api/v1/ai/settings", {
      method: "PATCH",
      body: JSON.stringify({
        default_provider: "gemini",
        gemini_model: "gemini-1.5-flash",
        openai_model: "gpt-4o-mini",
        gemini_api_key: null,
      }),
    });
  });
});
