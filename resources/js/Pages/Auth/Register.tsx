import { useEffect, useRef, useState } from "react";
import AuthShell from "./AuthShell";
import { formValues, submitJson } from "./auth-http";

type Props = {
    consent: {
        id: string;
        title: string;
        versionLabel: string;
        content: string;
    };
    csrfToken: string;
};

export default function Register({ consent, csrfToken }: Props) {
    const [registrationToken, setRegistrationToken] = useState("");
    const [verifiedEmail, setVerifiedEmail] = useState("");
    const [proofExpiresAt, setProofExpiresAt] = useState("");
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);
    const codeInput = useRef<HTMLInputElement>(null);
    const identityTypeInput = useRef<HTMLSelectElement>(null);
    const requestCodeButton = useRef<HTMLButtonElement>(null);
    const errorMessage = useRef<HTMLParagraphElement>(null);
    const requestGeneration = useRef(0);
    const signupInFlight = useRef(false);

    function clearVerification() {
        setRegistrationToken("");
        setVerifiedEmail("");
        setProofExpiresAt("");
    }

    useEffect(() => {
        if (!error) return;
        errorMessage.current?.focus();
    }, [error]);

    useEffect(() => {
        if (registrationToken) identityTypeInput.current?.focus();
    }, [registrationToken]);

    useEffect(() => {
        if (!proofExpiresAt) return;

        const expiresAt = Date.parse(proofExpiresAt);
        const expireProof = () => {
            if (signupInFlight.current) return;

            requestGeneration.current += 1;
            clearVerification();
            setBusy(false);
            setStatus(
                "การยืนยันอีเมลหมดอายุ กรุณาขอรหัสและยืนยันใหม่ก่อนกรอกข้อมูลบัญชีต่อ",
            );
            requestCodeButton.current?.focus();
        };
        const delay = expiresAt - Date.now();

        if (!Number.isFinite(expiresAt) || delay <= 0) {
            expireProof();
            return;
        }

        const timer = window.setTimeout(expireProof, delay);
        return () => window.clearTimeout(timer);
    }, [proofExpiresAt]);

    async function requestCode(form: HTMLFormElement) {
        const generation = ++requestGeneration.current;
        setBusy(true);
        setError("");
        clearVerification();
        const data = formValues(form);
        const result = await submitJson(
            "/auth/verification/request",
            csrfToken,
            { email: data.email },
        );
        if (generation !== requestGeneration.current) return;

        if (result.ok) {
            setStatus(String(result.body.message ?? ""));
            codeInput.current?.focus();
        } else {
            setError(String(result.body.message ?? "ไม่สามารถขอรหัสได้"));
        }
        setBusy(false);
    }

    async function verifyCode(form: HTMLFormElement) {
        const generation = ++requestGeneration.current;
        setBusy(true);
        setError("");
        const data = formValues(form);
        const result = await submitJson(
            "/auth/verification/verify",
            csrfToken,
            { email: data.email, code: data.code },
        );
        if (generation !== requestGeneration.current) return;

        if (result.ok) {
            setRegistrationToken(String(result.body.registration_token ?? ""));
            setVerifiedEmail(
                String(data.email ?? "")
                    .trim()
                    .toLowerCase(),
            );
            setProofExpiresAt(String(result.body.expires_at ?? ""));
            setStatus("ยืนยันอีเมลแล้ว กรุณากรอกข้อมูลบัญชีให้ครบ");
        } else {
            setError(String(result.body.message ?? "ไม่สามารถยืนยันได้"));
        }

        setBusy(false);
    }

    async function register(form: HTMLFormElement) {
        if (signupInFlight.current) return;

        setBusy(true);
        setError("");
        const data = formValues(form);
        if (
            !registrationToken ||
            verifiedEmail !==
                String(data.email ?? "")
                    .trim()
                    .toLowerCase() ||
            !proofExpiresAt ||
            Date.parse(proofExpiresAt) <= Date.now()
        ) {
            clearVerification();
            setError("การยืนยันอีเมลหมดอายุหรืออีเมลเปลี่ยน กรุณาขอรหัสใหม่");
            setBusy(false);
            return;
        }
        signupInFlight.current = true;
        const result = await submitJson("/signup", csrfToken, {
            ...data,
            registration_token: registrationToken,
            consent_accepted: data.consent_accepted === "yes",
            consent_version: consent.id,
        });
        signupInFlight.current = false;

        if (result.ok) {
            window.location.assign(String(result.body.redirect ?? "/account"));
            return;
        }

        if (Date.parse(proofExpiresAt) <= Date.now()) {
            clearVerification();
            setStatus(
                "การยืนยันอีเมลหมดอายุ กรุณาขอรหัสและยืนยันใหม่ก่อนกรอกข้อมูลบัญชีต่อ",
            );
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
                        aria-describedby={
                            error ? "registration-error" : undefined
                        }
                        aria-invalid={Boolean(error)}
                        onChange={(event) => {
                            if (
                                verifiedEmail &&
                                event.currentTarget.value
                                    .trim()
                                    .toLowerCase() !== verifiedEmail
                            ) {
                                requestGeneration.current += 1;
                                clearVerification();
                                setStatus("");
                            }
                        }}
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
                            ref={codeInput}
                            aria-describedby={
                                error ? "registration-error" : undefined
                            }
                            aria-invalid={Boolean(error)}
                            required
                        />
                    </div>
                    <button
                        type="button"
                        className="button-secondary"
                        disabled={busy}
                        ref={requestCodeButton}
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
                            ref={identityTypeInput}
                            aria-describedby={
                                error ? "registration-error" : undefined
                            }
                            aria-invalid={Boolean(error)}
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
                            aria-describedby={
                                error ? "registration-error" : undefined
                            }
                            aria-invalid={Boolean(error)}
                            required
                        />
                    </div>
                    <div className="field">
                        <label htmlFor="person_link_token">
                            รหัสเชื่อมบุคคลเดิม (ถ้ามี)
                        </label>
                        <input
                            id="person_link_token"
                            name="person_link_token"
                            type="password"
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <p className="field-help">
                            วางรหัสที่ได้รับจากผู้ดูแลในช่องนี้เท่านั้น
                            ห้ามส่งผ่านลิงก์
                        </p>
                    </div>
                    <div className="field-grid">
                        <div className="field">
                            <label htmlFor="given_name">ชื่อ</label>
                            <input
                                id="given_name"
                                name="given_name"
                                autoComplete="given-name"
                                aria-describedby={
                                    error ? "registration-error" : undefined
                                }
                                aria-invalid={Boolean(error)}
                                required
                            />
                        </div>
                        <div className="field">
                            <label htmlFor="family_name">นามสกุล</label>
                            <input
                                id="family_name"
                                name="family_name"
                                autoComplete="family-name"
                                aria-describedby={
                                    error ? "registration-error" : undefined
                                }
                                aria-invalid={Boolean(error)}
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
                            aria-describedby={
                                error ? "registration-error" : undefined
                            }
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
                            aria-describedby={
                                error ? "registration-error" : undefined
                            }
                            aria-invalid={Boolean(error)}
                            required
                        />
                    </div>
                    <label className="check-field">
                        <input
                            name="consent_accepted"
                            type="checkbox"
                            value="yes"
                            aria-describedby={
                                error ? "registration-error" : undefined
                            }
                            aria-invalid={Boolean(error)}
                            required
                        />
                        <span>
                            ฉันอ่านและยอมรับ{" "}
                            <a href="#registration-consent">{consent.title}</a>{" "}
                            รุ่น <strong>{consent.versionLabel}</strong>
                        </span>
                    </label>
                    <button type="submit" disabled={busy}>
                        {busy ? "กำลังสร้างบัญชี…" : "สร้างบัญชี"}
                    </button>
                </fieldset>
            </form>
            <section
                id="registration-consent"
                className="consent-document"
                aria-labelledby="registration-consent-title"
                tabIndex={-1}
            >
                <h2 id="registration-consent-title">{consent.title}</h2>
                <p>
                    รุ่น <strong>{consent.versionLabel}</strong>
                </p>
                <p>{consent.content}</p>
            </section>
            <p className="form-status" role="status" aria-live="polite">
                {status}
            </p>
            {error && (
                <p
                    id="registration-error"
                    className="form-error"
                    role="alert"
                    tabIndex={-1}
                    ref={errorMessage}
                >
                    {error}
                </p>
            )}
            <p className="auth-links">
                มีบัญชีแล้ว <a href="/signin">เข้าสู่ระบบ</a>
            </p>
        </AuthShell>
    );
}
