import { useState } from "react";
import AuthShell from "./AuthShell";
import { formValues, submitJson } from "./auth-http";

type Props = { csrfToken: string };

export default function Forgot({ csrfToken }: Props) {
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);

    return (
        <AuthShell title="กู้คืนการเข้าถึง" eyebrow="ความปลอดภัยบัญชี">
            <p className="lede">
                ระบุอีเมล ระบบจะแจ้งผลแบบเดียวกันไม่ว่าจะพบบัญชีหรือไม่
            </p>
            <form
                className="auth-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    setBusy(true);
                    setStatus("");
                    setError("");
                    const data = formValues(event.currentTarget);
                    void submitJson("/forgot", csrfToken, data).then(
                        (result) => {
                            if (result.ok) {
                                setStatus(String(result.body.message ?? ""));
                            } else {
                                setError(
                                    String(
                                        result.body.message ??
                                            "ไม่สามารถขอลิงก์กู้คืนได้",
                                    ),
                                );
                            }
                            setBusy(false);
                        },
                    );
                }}
            >
                <div className="field">
                    <label htmlFor="email">อีเมล</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        required
                    />
                </div>
                <button type="submit" disabled={busy}>
                    {busy ? "กำลังดำเนินการ…" : "ขอลิงก์กู้คืน"}
                </button>
            </form>
            <p className="form-status" role="status" aria-live="polite">
                {status}
            </p>
            {error && (
                <p className="form-error" role="alert">
                    {error}
                </p>
            )}
            <p className="auth-links">
                <a href="/signin">กลับไปเข้าสู่ระบบ</a>
            </p>
        </AuthShell>
    );
}
