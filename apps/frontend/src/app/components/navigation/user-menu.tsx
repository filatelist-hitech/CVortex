"use client";

import { useRef, useState, useEffect, useCallback, type MouseEvent } from "react";
import Link from "next/link";
import { NavIcon } from "./nav-icons";

export type UserMenuProps = {
  email: string;
  role: string;
  onSignOut: () => Promise<void>;
  currentDestination?: string;
  allowNavigation?: () => boolean;
};

export function UserMenu({
  email,
  role,
  onSignOut,
  currentDestination,
  allowNavigation = () => true,
}: UserMenuProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [signingOut, setSigningOut] = useState(false);
  const [feedback, setFeedback] = useState("");
  const [error, setError] = useState("");

  const triggerRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLDialogElement>(null);
  const backdropPressRef = useRef(false);

  const isAdmin = role === "admin";
  const userInitial = (email ? email.charAt(0) : "U").toUpperCase();
  const roleLabel = isAdmin ? "Администратор" : "Пользователь";

  const closeDialog = useCallback(() => {
    const dialog = dialogRef.current;
    if (dialog && dialog.open) {
      dialog.close();
    }
    setIsOpen(false);
    triggerRef.current?.focus();
  }, []);

  const openDialog = useCallback(() => {
    setError("");
    setFeedback("");
    const dialog = dialogRef.current;
    if (dialog && !dialog.open) {
      dialog.showModal();
    }
    setIsOpen(true);
  }, []);

  const toggleDialog = useCallback(() => {
    if (isOpen) {
      closeDialog();
    } else {
      openDialog();
    }
  }, [isOpen, closeDialog, openDialog]);

  async function handleSignOut() {
    if (!allowNavigation()) {
      setFeedback("Сохраните или отмените несохранённую правку перед выходом.");
      return;
    }
    setSigningOut(true);
    setError("");
    setFeedback("");
    try {
      await onSignOut();
      closeDialog();
    } catch {
      setError("Не удалось выйти. Попробуйте ещё раз.");
    } finally {
      setSigningOut(false);
    }
  }

  function handleNavigationClick(event: MouseEvent<HTMLAnchorElement>) {
    if (!allowNavigation()) {
      event.preventDefault();
      return;
    }
    closeDialog();
  }

  // Handle outside click on <dialog>::backdrop
  function handleDialogPointerDown(event: React.PointerEvent<HTMLDialogElement>) {
    if (event.target === event.currentTarget) {
      const rect = event.currentTarget.getBoundingClientRect();
      const isOutside =
        event.clientX < rect.left ||
        event.clientX > rect.right ||
        event.clientY < rect.top ||
        event.clientY > rect.bottom;
      backdropPressRef.current = isOutside;
    } else {
      backdropPressRef.current = false;
    }
  }

  function handleDialogClick(event: React.MouseEvent<HTMLDialogElement>) {
    if (event.target === event.currentTarget && backdropPressRef.current) {
      const rect = event.currentTarget.getBoundingClientRect();
      const isOutside =
        event.clientX < rect.left ||
        event.clientX > rect.right ||
        event.clientY < rect.top ||
        event.clientY > rect.bottom;
      if (isOutside) {
        closeDialog();
      }
    }
    backdropPressRef.current = false;
  }

  // Keydown trap & escape
  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      if (!isOpen) return;
      if (event.key === "Escape") {
        event.preventDefault();
        closeDialog();
      }
    }
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [isOpen, closeDialog]);

  return (
    <div className="user-menu-root">
      {/* Account trigger button matching existing IDs & classes for backward-compatibility */}
      <button
        ref={triggerRef}
        id="account-trigger"
        type="button"
        className="workspace-account-trigger account-trigger"
        aria-haspopup="dialog"
        aria-controls="account-dialog"
        aria-expanded={isOpen}
        onClick={toggleDialog}
      >
        <span className="workspace-avatar account-avatar" aria-hidden="true">
          {userInitial}
        </span>
        <span className="trigger-label-group">
          <strong className="trigger-title type-label">Профиль</strong>
          <small id="account-role-label" className="trigger-role type-caption">
            {roleLabel}
          </small>
        </span>
        <NavIcon name="chevron-down" className="trigger-chevron" />
      </button>

      {/* Account Modal Dialog matching existing IDs & classes */}
      <dialog
        ref={dialogRef}
        id="account-dialog"
        className="workspace-account-dialog account-dialog"
        aria-labelledby="account-title"
        aria-describedby="account-description"
        onPointerDown={handleDialogPointerDown}
        onClick={handleDialogClick}
        onClose={() => {
          setIsOpen(false);
          triggerRef.current?.focus();
        }}
      >
        <div className="user-menu-panel">
          {/* Header & Identity Card */}
          <div className="user-menu-header">
            <div className="user-menu-identity">
              <span className="user-menu-avatar" aria-hidden="true">
                {userInitial}
              </span>
              <div className="user-menu-identity-info">
                <p className="user-menu-eyebrow type-caption">Аккаунт CVortex</p>
                <strong id="account-title" className="user-menu-email">
                  {email}
                </strong>
                <span
                  className={`user-menu-role-badge ${isAdmin ? "admin" : "user"}`}
                >
                  {roleLabel}
                </span>
              </div>
            </div>
            <button
              id="close-account"
              type="button"
              className="user-menu-close-btn secondary"
              onClick={closeDialog}
              aria-label="Закрыть профиль"
            >
              <NavIcon name="close" className="close-icon" />
            </button>
          </div>

          <p id="account-description" className="user-menu-description subtle type-caption">
            Вход, выход и права принадлежат текущему аккаунту.
          </p>

          {/* Account Actions Section */}
          <div className="user-menu-section" role="group" aria-label="Управление профилем">
            <div className="user-menu-section-title">Аккаунт</div>
            <div className="user-menu-items">
              <button
                type="button"
                className="user-menu-item disabled-action"
                aria-label="Мой профиль"
                onClick={() =>
                  setFeedback("Редактирование профиля пока недоступно. Карьерные факты не меняются.")
                }
              >
                <div className="user-menu-item-icon">
                  <NavIcon name="user" />
                </div>
                <div className="user-menu-item-text">
                  <span className="user-menu-item-title">Мой профиль</span>
                  <span className="user-menu-item-subtitle type-caption" aria-hidden="true">Пока недоступен</span>
                </div>
              </button>

              <Link
                href="/settings"
                className={`user-menu-item ${currentDestination === "settings" ? "active" : ""}`}
                aria-label="Настройки AI"
                aria-current={currentDestination === "settings" ? "page" : undefined}
                onClick={handleNavigationClick}
              >
                <div className="user-menu-item-icon">
                  <NavIcon name="settings" />
                </div>
                <div className="user-menu-item-text">
                  <span className="user-menu-item-title">Настройки AI</span>
                  <span className="user-menu-item-subtitle type-caption" aria-hidden="true">Провайдеры и API-ключи</span>
                </div>
              </Link>
            </div>
          </div>

          {/* Admin Utilities Section */}
          {isAdmin && (
            <div className="user-menu-section admin-section" role="group" aria-label="Панель администратора">
              <div className="user-menu-section-title admin-title">
                <NavIcon name="shield" className="section-icon" />
                <span>Для администратора</span>
              </div>
              <div className="user-menu-items">
                <Link
                  href="/diagnostics"
                  className={`user-menu-item ${currentDestination === "diagnostics" ? "active" : ""}`}
                  aria-label="Диагностика"
                  aria-current={currentDestination === "diagnostics" ? "page" : undefined}
                  onClick={handleNavigationClick}
                >
                  <div className="user-menu-item-icon">
                    <NavIcon name="activity" />
                  </div>
                  <div className="user-menu-item-text">
                    <span className="user-menu-item-title">Диагностика</span>
                    <span className="user-menu-item-subtitle type-caption" aria-hidden="true">Ошибки и состояние операций</span>
                  </div>
                </Link>

                <Link
                  href="/admin/settings"
                  className={`user-menu-item ${currentDestination === "admin-settings" ? "active" : ""}`}
                  aria-label="Настройки системы"
                  aria-current={currentDestination === "admin-settings" ? "page" : undefined}
                  onClick={handleNavigationClick}
                >
                  <div className="user-menu-item-icon">
                    <NavIcon name="sliders" />
                  </div>
                  <div className="user-menu-item-text">
                    <span className="user-menu-item-title">Настройки системы</span>
                    <span className="user-menu-item-subtitle type-caption" aria-hidden="true">Конфигурация и среда</span>
                  </div>
                </Link>
              </div>
            </div>
          )}

          {/* Feedback or Error messages */}
          {feedback && (
            <p className="user-menu-feedback type-secondary" role="status" aria-live="polite">
              {feedback}
            </p>
          )}
          {error && (
            <p className="user-menu-error error" role="alert">
              {error}
            </p>
          )}

          {/* Session Actions Section */}
          <div className="user-menu-footer">
            <button
              type="button"
              className="user-menu-logout-btn secondary"
              disabled={signingOut}
              onClick={() => void handleSignOut()}
            >
              <NavIcon name="log-out" />
              <span>{signingOut ? "Выход…" : "Выйти"}</span>
            </button>
          </div>
        </div>
      </dialog>
    </div>
  );
}
