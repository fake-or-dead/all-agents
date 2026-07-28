import { useState } from "react";
import AuthShell from "./AuthShell";
import { formValues, submitJson } from "./auth-http";

type Props = {
    consentVersion: string;
    csrfToken: string;
};

export default function Register({ consentVersion, csrfToken }: Props) {
    const [registrationToken, setRegistrationToken] = useState("");
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);

    async function requestCode(form: HTMLFormElement) {
        setBusy(true);
        setError("");
        const data = formValues(form);
        const result = await submitJson(
            "/auth/verification/request",
            csrfToken,
            { email: data.email },
        );
        if (result.ok) {
            setStatus(String(result.body.message ?? ""));
        } else {
            setError(String(result.body.message ?? "ไม่สามารถขอรหัสได้"));
        }
        setBusy(false);
    }

    async function verifyCode(form: HTMLFormElement) {
        setBusy(true);
        setError("");
        const data = formValues(form);
        const result = await submitJson(
            "/auth/verification/verify",
            csrfToken,
            { email: data.email, code: data.code },
        );

        if (result.ok) {
            setRegistrationToken(String(result.body.registration_token ?? ""));
            setStatus("ยืนยันอีเมลแล้ว กรุณากรอกข้อมูลบัญชีให้ครบ");
        } else {
            setError(String(result.body.message ?? "ไม่สามารถยืนยันได้"));
        }

        setBusy(false);
    }

    async function register(form: HTMLFormElement) {
        setBusy(true);
        setError("");
        const data = formValues(form);
        const result = await submitJson("/signup", csrfToken, {
            ...data,
            registration_token: registrationToken,
            consent_accepted: data.consent_accepted === "yes",
            consent_version: consentVersion,
        });

        if (result.ok) {
            window.location.assign(String(result.body.redirect ?? "/account"));
            return;
        }

        setError(
            String(
                result.body.message ??
                    "กรุณาตรวจสอบข้อมูลที่กรอก แล้วลองอีกครั้ง",
            ),
        );
        setBusy(false);
    }

    return (
        <AuthShell title="สร้างบัญชี" eyebrow="บัญชีผู้สมัคร">
            <p className="lede">
                ยืนยันอีเมลก่อน แล้วจึงบันทึกข้อมูลประจำตัวและความยินยอม
            </p>
            <form
                className="auth-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    void register(event.currentTarget);
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
                <div className="verification-row">
                    <div className="field">
                        <label htmlFor="code">รหัสยืนยัน 6 หลัก</label>
                        <input
                            id="code"
                            name="code"
                            inputMode="numeric"
                            pattern="[0-9]{6}"
                            maxLength={6}
                            autoComplete="one-time-code"
                            required
                        />
                    </div>
                    <button
                        type="button"
                        className="button-secondary"
                        disabled={busy}
                        onClick={(event) =>
                            void requestCode(event.currentTarget.form!)
                        }
                    >
                        ขอรหัส
                    </button>
                    <button
                        type="button"
                        className="button-secondary"
                        disabled={busy}
                        onClick={(event) =>
                            void verifyCode(event.currentTarget.form!)
                        }
                    >
                        ยืนยันรหัส
                    </button>
                </div>

                <fieldset disabled={!registrationToken || busy}>
                    <legend>ข้อมูลบัญชี</legend>
                    <div className="field">
                        <label htmlFor="identity_type">
                            ประเภทเอกสารประจำตัว
                        </label>
                        <select
                            id="identity_type"
                            name="identity_type"
                            required
                        >
                            <option value="personal_id">
                                เลขประจำตัวประชาชน
                            </option>
                            <option value="passport">หนังสือเดินทาง</option>
                        </select>
                    </div>
                    <div className="field">
                        <label htmlFor="identity_number">
                            เลขเอกสารประจำตัว
                        </label>
                        <input
                            id="identity_number"
                            name="identity_number"
                            autoComplete="off"
                            required
                        />
                    </div>
                    <div className="field-grid">
                        <div className="field">
                            <label htmlFor="given_name">ชื่อ</label>
                            <input
                                id="given_name"
                                name="given_name"
                                autoComplete="given-name"
                                required
                            />
                        </div>
                        <div className="field">
                            <label htmlFor="family_name">นามสกุล</label>
                            <input
                                id="family_name"
                                name="family_name"
                                autoComplete="family-name"
                                required
                            />
                        </div>
                    </div>
                    <div className="field">
                        <label htmlFor="password">
                            รหัสผ่านใหม่ (อย่างน้อย 12 ตัวอักษร
                            มีตัวอักษรและตัวเลข)
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            minLength={12}
                            autoComplete="new-password"
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
                            required
                        />
                    </div>
                    <label className="check-field">
                        <input
                            name="consent_accepted"
                            type="checkbox"
                            value="yes"
                            required
                        />
                        <span>
                            ฉันอ่านและยอมรับเอกสารความยินยอม รุ่น{" "}
                            <strong>{consentVersion}</strong>
                        </span>
                    </label>
                    <button type="submit" disabled={busy}>
                        {busy ? "กำลังสร้างบัญชี…" : "สร้างบัญชี"}
                    </button>
                </fieldset>
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
                มีบัญชีแล้ว <a href="/signin">เข้าสู่ระบบ</a>
            </p>
        </AuthShell>
    );
}
