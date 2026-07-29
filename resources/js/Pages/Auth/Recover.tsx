import { useEffect, useRef, useState } from "react";
import AuthShell from "./AuthShell";
import { formValues, submitJson } from "./auth-http";

type Props = { csrfToken: string; token: string };

export default function Recover({ csrfToken, token }: Props) {
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const errorMessage = useRef<HTMLParagraphElement>(null);

    useEffect(() => {
        if (error) errorMessage.current?.focus();
    }, [error]);

    return (
        <AuthShell title="ตั้งรหัสผ่านใหม่" eyebrow="ความปลอดภัยบัญชี">
            <p className="lede">
                ลิงก์นี้ใช้ได้ครั้งเดียวและมีเวลาจำกัด
                เมื่อสำเร็จระบบจะออกจากระบบทุกอุปกรณ์
            </p>
            <form
                className="auth-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    setBusy(true);
                    setError("");
                    const data = formValues(event.currentTarget);
                    void submitJson(
                        `/recover/${encodeURIComponent(token)}`,
                        csrfToken,
                        data,
                    ).then((result) => {
                        if (result.ok) {
                            window.location.assign(
                                String(result.body.redirect ?? "/signin"),
                            );
                            return;
                        }
                        setError(
                            String(
                                result.body.message ??
                                    "ไม่สามารถตั้งรหัสผ่านใหม่ได้",
                            ),
                        );
                        setBusy(false);
                    });
                }}
            >
                <div className="field">
                    <label htmlFor="password">รหัสผ่านใหม่</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        minLength={12}
                        autoComplete="new-password"
                        aria-describedby={error ? "recover-error" : undefined}
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
                        aria-describedby={error ? "recover-error" : undefined}
                        aria-invalid={Boolean(error)}
                        required
                    />
                </div>
                <button type="submit" disabled={busy}>
                    {busy ? "กำลังบันทึก…" : "ตั้งรหัสผ่านใหม่"}
                </button>
            </form>
            {error && (
                <p
                    id="recover-error"
                    className="form-error"
                    role="alert"
                    tabIndex={-1}
                    ref={errorMessage}
                >
                    {error}
                </p>
            )}
        </AuthShell>
    );
}
