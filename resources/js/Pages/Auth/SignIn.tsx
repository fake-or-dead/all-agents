import { useState } from "react";
import AuthShell from "./AuthShell";
import { formValues, submitJson } from "./auth-http";

type Props = { csrfToken: string };

export default function SignIn({ csrfToken }: Props) {
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);

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
                    <select id="identity_type" name="identity_type">
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
                        required
                    />
                </div>
                <button type="submit" disabled={busy}>
                    {busy ? "กำลังตรวจสอบ…" : "เข้าสู่ระบบ"}
                </button>
            </form>
            {error && (
                <p className="form-error" role="alert">
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
