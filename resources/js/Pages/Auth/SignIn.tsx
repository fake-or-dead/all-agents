import { useEffect, useRef, useState } from "react";
import AuthShell from "./AuthShell";
import { formValues, submitJson } from "./auth-http";

type Props = { csrfToken: string };

export default function SignIn({ csrfToken }: Props) {
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const errorMessage = useRef<HTMLParagraphElement>(null);

    useEffect(() => {
        if (error) errorMessage.current?.focus();
    }, [error]);

    return (
        <AuthShell title="เข้าสู่ระบบ" eyebrow="บัญชีผู้สมัคร">
            <p className="lede">
                ใช้เลขประจำตัวหรือหนังสือเดินทางที่ลงทะเบียนไว้
            </p>
            <form
                className="auth-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    setBusy(true);
                    setError("");
                    const data = formValues(event.currentTarget);
                    void submitJson("/signin", csrfToken, data).then(
                        (result) => {
                            if (result.ok) {
                                window.location.assign(
                                    String(result.body.redirect ?? "/account"),
                                );
                                return;
                            }
                            setError(
                                String(
                                    result.body.message ??
                                        "ไม่สามารถเข้าสู่ระบบได้",
                                ),
                            );
                            setBusy(false);
                        },
                    );
                }}
            >
                <div className="field">
                    <label htmlFor="identity_type">ประเภทเอกสารประจำตัว</label>
                    <select
                        id="identity_type"
                        name="identity_type"
                        aria-describedby={error ? "signin-error" : undefined}
                        aria-invalid={Boolean(error)}
                    >
                        <option value="personal_id">เลขประจำตัวประชาชน</option>
                        <option value="passport">หนังสือเดินทาง</option>
                    </select>
                </div>
                <div className="field">
                    <label htmlFor="identity_number">เลขเอกสารประจำตัว</label>
                    <input
                        id="identity_number"
                        name="identity_number"
                        autoComplete="username"
                        aria-describedby={error ? "signin-error" : undefined}
                        aria-invalid={Boolean(error)}
                        required
                    />
                </div>
                <div className="field">
                    <label htmlFor="password">รหัสผ่าน</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autoComplete="current-password"
                        aria-describedby={error ? "signin-error" : undefined}
                        aria-invalid={Boolean(error)}
                        required
                    />
                </div>
                <button type="submit" disabled={busy}>
                    {busy ? "กำลังตรวจสอบ…" : "เข้าสู่ระบบ"}
                </button>
            </form>
            {error && (
                <p
                    id="signin-error"
                    className="form-error"
                    role="alert"
                    tabIndex={-1}
                    ref={errorMessage}
                >
                    {error}
                </p>
            )}
            <p className="auth-links">
                <a href="/forgot">ลืมรหัสผ่าน</a>
                <span aria-hidden="true"> · </span>
                <a href="/signup">สร้างบัญชี</a>
            </p>
        </AuthShell>
    );
}
