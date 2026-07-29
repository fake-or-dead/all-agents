import { useEffect, useRef, useState } from "react";
import AuthShell from "./AuthShell";
import { formValues, submitJson } from "./auth-http";

type Props = { csrfToken: string };

export default function Account({ csrfToken }: Props) {
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const [signingOut, setSigningOut] = useState(false);
    const errorMessage = useRef<HTMLParagraphElement>(null);

    useEffect(() => {
        if (error) errorMessage.current?.focus();
    }, [error]);

    return (
        <AuthShell title="ความปลอดภัยบัญชี" eyebrow="บัญชีของฉัน">
            <form
                className="auth-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    setBusy(true);
                    setStatus("");
                    setError("");
                    const data = formValues(event.currentTarget);
                    void submitJson("/account/password", csrfToken, data).then(
                        (result) => {
                            if (result.ok) {
                                setStatus(String(result.body.message ?? ""));
                            } else {
                                setError(
                                    String(
                                        result.body.message ??
                                            "ไม่สามารถเปลี่ยนรหัสผ่านได้",
                                    ),
                                );
                            }
                            setBusy(false);
                        },
                    );
                }}
            >
                <div className="field">
                    <label htmlFor="current_password">รหัสผ่านปัจจุบัน</label>
                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        autoComplete="current-password"
                        aria-describedby={error ? "account-error" : undefined}
                        aria-invalid={Boolean(error)}
                        required
                    />
                </div>
                <div className="field">
                    <label htmlFor="password">รหัสผ่านใหม่</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        minLength={12}
                        autoComplete="new-password"
                        aria-describedby={error ? "account-error" : undefined}
                        aria-invalid={Boolean(error)}
                        required
                    />
                </div>
                <div className="field">
                    <label htmlFor="password_confirmation">
                        ยืนยันรหัสผ่านใหม่
                    </label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        minLength={12}
                        autoComplete="new-password"
                        aria-describedby={error ? "account-error" : undefined}
                        aria-invalid={Boolean(error)}
                        required
                    />
                </div>
                <button type="submit" disabled={busy || signingOut}>
                    {busy ? "กำลังเปลี่ยนรหัสผ่าน…" : "เปลี่ยนรหัสผ่าน"}
                </button>
            </form>
            <p className="form-status" role="status" aria-live="polite">
                {status}
            </p>
            {error && (
                <p
                    id="account-error"
                    className="form-error"
                    role="alert"
                    tabIndex={-1}
                    ref={errorMessage}
                >
                    {error}
                </p>
            )}
            <button
                type="button"
                className="button-danger"
                disabled={busy || signingOut}
                onClick={() => {
                    setSigningOut(true);
                    setError("");
                    void submitJson("/signout", csrfToken, {}).then(
                        (result) => {
                            if (result.ok) {
                                window.location.assign(
                                    String(result.body.redirect ?? "/signin"),
                                );
                                return;
                            }
                            setError(
                                String(
                                    result.body.message ??
                                        "ไม่สามารถออกจากระบบได้",
                                ),
                            );
                            setSigningOut(false);
                        },
                    );
                }}
            >
                {signingOut ? "กำลังออกจากระบบ…" : "ออกจากระบบ"}
            </button>
        </AuthShell>
    );
}
