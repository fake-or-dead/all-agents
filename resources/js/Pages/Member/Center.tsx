import { Head } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";
import { formValues, requestJson, submitJson } from "../Auth/auth-http";

type ReferenceItem = {
    id: string;
    code: string;
    label: string;
    postcode?: string;
};
type ReferenceLoadState = "idle" | "loading" | "error";
type ReferenceParentType = "province" | "amphoe";

function parseReferenceEnvelope(
    value: unknown,
    expectedParentType: ReferenceParentType,
    expectedParentId: string,
    requiresPostcode: boolean,
): ReferenceItem[] {
    if (
        !isRecord(value) ||
        !hasExactKeys(value, ["data", "errors", "meta"]) ||
        !Array.isArray(value.data) ||
        !Array.isArray(value.errors) ||
        value.errors.length !== 0 ||
        !isRecord(value.meta) ||
        !hasExactKeys(value.meta, ["parent_id", "parent_type", "status"]) ||
        value.meta.parent_type !== expectedParentType ||
        value.meta.parent_id !== expectedParentId ||
        !["ok", "empty", "unknown-parent"].includes(
            typeof value.meta.status === "string" ? value.meta.status : "",
        )
    ) {
        throw new Error("invalid reference response");
    }

    if (value.meta.status !== "ok" && value.data.length !== 0) {
        throw new Error("invalid reference response");
    }

    const items: ReferenceItem[] = [];
    const ids = new Set<string>();
    const expectedItemKeys = requiresPostcode
        ? ["code", "id", "label", "postcode"]
        : ["code", "id", "label"];

    for (const candidate of value.data) {
        if (
            !isRecord(candidate) ||
            !hasExactKeys(candidate, expectedItemKeys) ||
            !isBoundedString(candidate.id, 16) ||
            !/^[a-z0-9-]{1,16}$/.test(candidate.id) ||
            ids.has(candidate.id) ||
            !isBoundedString(candidate.code, 8) ||
            !isBoundedString(candidate.label, 255) ||
            (requiresPostcode &&
                (typeof candidate.postcode !== "string" ||
                    !/^\d{5}$/.test(candidate.postcode)))
        ) {
            throw new Error("invalid reference response");
        }

        ids.add(candidate.id);
        items.push(
            requiresPostcode
                ? {
                      id: candidate.id,
                      code: candidate.code,
                      label: candidate.label,
                      postcode: candidate.postcode as string,
                  }
                : {
                      id: candidate.id,
                      code: candidate.code,
                      label: candidate.label,
                  },
        );
    }

    return items;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === "object" && value !== null && !Array.isArray(value);
}

function hasExactKeys(
    value: Record<string, unknown>,
    expected: string[],
): boolean {
    const keys = Object.keys(value).sort();
    return (
        keys.length === expected.length &&
        keys.every((key, index) => key === expected[index])
    );
}

function isBoundedString(
    value: unknown,
    maximumLength: number,
): value is string {
    return (
        typeof value === "string" &&
        value.trim() !== "" &&
        value.length <= maximumLength
    );
}
type Address = {
    addressLine1: string;
    addressLine2: string | null;
    provinceId: string;
    amphoeId: string;
    tambonId: string;
    postcode: string;
    version: number;
};
type Profile = {
    personId: string;
    givenName: string;
    familyName: string;
    version: number;
    identifier: { type: string; countryCode: string; masked: string };
    contact: { email: string | null; phone: string | null; version: number };
    address: Address | null;
};
type Training = {
    id: string;
    courseName: string;
    providerName: string;
    startedOn: string;
    endedOn: string | null;
    version: number;
};
type Application = {
    courseSessionId: string;
    state: string;
    nextTask: string | null;
    nextTaskUnavailableReason: string | null;
    deadline: string | null;
    deadlineUnavailableReason: string | null;
    lastSavedAt: string | null;
    lastSavedAtUnavailableReason: string | null;
    resumeUrl: string | null;
    resumeUnavailableReason: string | null;
    history: {
        state: string;
        occurredAt: string | null;
        occurredAtUnavailableReason: string | null;
    }[];
};
type FieldErrors = Record<string, string>;
type ValidationState = { scope: string; errors: FieldErrors };
type Props = {
    activeTab: "profile" | "applications" | "training" | "password";
    profile: Profile;
    training: Training[];
    applications: Application[];
    references: { provinces: ReferenceItem[] };
    csrfToken: string;
};

const tabs = [
    ["profile", "ข้อมูลส่วนตัว"],
    ["applications", "ใบสมัครและประวัติ"],
    ["training", "ประวัติการอบรม"],
    ["password", "รหัสผ่าน"],
] as const;

export default function Center(props: Props) {
    const [profile, setProfile] = useState(props.profile);
    const [training, setTraining] = useState(props.training);
    const [status, setStatus] = useState("");
    const [error, setError] = useState("");
    const [validation, setValidation] = useState<ValidationState>({
        scope: "",
        errors: {},
    });
    const [busy, setBusy] = useState(false);
    const errorRef = useRef<HTMLParagraphElement>(null);

    useEffect(() => {
        if (error && Object.keys(validation.errors).length === 0) {
            errorRef.current?.focus();
        }
    }, [error, validation]);

    const finish = (
        result: Awaited<ReturnType<typeof requestJson>>,
        onSuccess: () => void,
        form?: HTMLFormElement,
    ) => {
        if (result.ok) {
            onSuccess();
            setValidation({ scope: "", errors: {} });
            setStatus(String(result.body.message ?? "บันทึกข้อมูลแล้ว"));
        } else {
            const fieldErrors =
                result.status === 422
                    ? validationErrors(result.body.errors)
                    : {};
            setValidation({
                scope: form?.dataset.validationScope ?? "",
                errors: fieldErrors,
            });
            setError(
                result.status === 422
                    ? String(result.body.message ?? "กรุณาตรวจสอบข้อมูลที่ระบุ")
                    : result.status === 409
                      ? "ข้อมูลถูกแก้ไขจากอุปกรณ์อื่น กรุณาโหลดใหม่"
                      : String(result.body.message ?? "บันทึกข้อมูลไม่สำเร็จ"),
            );
            const firstField = Object.keys(fieldErrors)[0];
            if (firstField && form) {
                window.requestAnimationFrame(() => {
                    const control = form.elements.namedItem(firstField);
                    if (control instanceof HTMLElement) control.focus();
                });
            }
        }
        setBusy(false);
    };

    return (
        <>
            <Head title="บัญชีสมาชิก" />
            <a className="skip-link" href="#member-panel">
                ข้ามไปยังเนื้อหาหลัก
            </a>
            <header className="site-header">
                <div className="header-inner">
                    <a className="wordmark" href="/">
                        Tapoda Next
                    </a>
                    <nav aria-label="บัญชี">
                        <a href="/account">ความปลอดภัยบัญชี</a>
                    </nav>
                </div>
            </header>
            <main className="member-shell">
                <header className="member-heading">
                    <p className="eyebrow">บัญชีของฉัน</p>
                    <h1>ข้อมูลสมาชิก</h1>
                    <p className="lede">
                        แก้ไขข้อมูลปัจจุบัน ดูประวัติ และจัดการความปลอดภัย
                    </p>
                </header>
                <nav className="member-tabs" aria-label="ส่วนข้อมูลสมาชิก">
                    {tabs.map(([id, label]) => (
                        <a
                            key={id}
                            href={`/member/${id}`}
                            aria-current={
                                props.activeTab === id ? "page" : undefined
                            }
                        >
                            {label}
                        </a>
                    ))}
                </nav>
                <section
                    id="member-panel"
                    className="member-panel"
                    tabIndex={-1}
                    aria-busy={busy}
                >
                    {props.activeTab === "profile" && (
                        <ProfilePanel
                            profile={profile}
                            provinces={props.references.provinces}
                            csrfToken={props.csrfToken}
                            busy={busy}
                            begin={() => {
                                setBusy(true);
                                setStatus("");
                                setError("");
                                setValidation({ scope: "", errors: {} });
                            }}
                            finish={finish}
                            validation={validation}
                            updateProfile={setProfile}
                        />
                    )}
                    {props.activeTab === "applications" && (
                        <ApplicationsPanel applications={props.applications} />
                    )}
                    {props.activeTab === "training" && (
                        <TrainingPanel
                            training={training}
                            csrfToken={props.csrfToken}
                            busy={busy}
                            begin={() => {
                                setBusy(true);
                                setStatus("");
                                setError("");
                                setValidation({ scope: "", errors: {} });
                            }}
                            finish={finish}
                            validation={validation}
                            add={(item) =>
                                setTraining((current) =>
                                    [...current, item].sort((a, b) =>
                                        b.startedOn.localeCompare(a.startedOn),
                                    ),
                                )
                            }
                            replace={(item) =>
                                setTraining((current) =>
                                    current
                                        .map((candidate) =>
                                            candidate.id === item.id
                                                ? item
                                                : candidate,
                                        )
                                        .sort((a, b) =>
                                            b.startedOn.localeCompare(
                                                a.startedOn,
                                            ),
                                        ),
                                )
                            }
                        />
                    )}
                    {props.activeTab === "password" && (
                        <PasswordPanel
                            csrfToken={props.csrfToken}
                            busy={busy}
                            begin={() => {
                                setBusy(true);
                                setStatus("");
                                setError("");
                                setValidation({ scope: "", errors: {} });
                            }}
                            finish={finish}
                            validation={validation}
                        />
                    )}
                    <p className="form-status" role="status" aria-live="polite">
                        {busy ? "กำลังบันทึก…" : status}
                    </p>
                    {error && (
                        <p
                            className="form-error"
                            role="alert"
                            tabIndex={-1}
                            ref={errorRef}
                        >
                            {error}{" "}
                            {Object.keys(validation.errors).length === 0 && (
                                <button
                                    type="button"
                                    className="button-secondary"
                                    onClick={() => window.location.reload()}
                                >
                                    โหลดใหม่
                                </button>
                            )}
                        </p>
                    )}
                </section>
            </main>
        </>
    );
}

function ProfilePanel({
    profile,
    provinces,
    csrfToken,
    busy,
    begin,
    finish,
    validation,
    updateProfile,
}: {
    profile: Profile;
    provinces: ReferenceItem[];
    csrfToken: string;
    busy: boolean;
    begin: () => void;
    finish: (
        result: Awaited<ReturnType<typeof requestJson>>,
        onSuccess: () => void,
        form?: HTMLFormElement,
    ) => void;
    validation: ValidationState;
    updateProfile: (profile: Profile) => void;
}) {
    const [amphoes, setAmphoes] = useState<ReferenceItem[]>([]);
    const [tambons, setTambons] = useState<ReferenceItem[]>([]);
    const [provinceId, setProvinceId] = useState(
        profile.address?.provinceId ?? "",
    );
    const [amphoeId, setAmphoeId] = useState(profile.address?.amphoeId ?? "");
    const [tambonId, setTambonId] = useState(profile.address?.tambonId ?? "");
    const [amphoeLoadState, setAmphoeLoadState] =
        useState<ReferenceLoadState>("idle");
    const [tambonLoadState, setTambonLoadState] =
        useState<ReferenceLoadState>("idle");
    const amphoeRequest = useRef<AbortController>(null);
    const tambonRequest = useRef<AbortController>(null);
    const amphoeErrorRef = useRef<HTMLDivElement>(null);
    const tambonErrorRef = useRef<HTMLDivElement>(null);
    const address = profile.address;

    const loadTambons = useCallback(
        async (parentId: string, preservedId = "") => {
            tambonRequest.current?.abort();
            const request = new AbortController();
            tambonRequest.current = request;
            setTambonLoadState("loading");

            try {
                const response = await fetch(
                    `/select/tambons?amphoe_id=${encodeURIComponent(parentId)}`,
                    {
                        headers: { Accept: "application/json" },
                        signal: request.signal,
                    },
                );
                if (!response.ok) throw new Error("reference request failed");

                const body: unknown = await response.json();
                const items = parseReferenceEnvelope(
                    body,
                    "amphoe",
                    parentId,
                    true,
                );
                if (
                    request.signal.aborted ||
                    tambonRequest.current !== request
                ) {
                    return;
                }

                setTambons(items);
                if (
                    preservedId !== "" &&
                    !items.some((item) => item.id === preservedId)
                ) {
                    setTambonId("");
                }
                setTambonLoadState("idle");
            } catch {
                if (
                    request.signal.aborted ||
                    tambonRequest.current !== request
                ) {
                    return;
                }
                setTambonLoadState("error");
            }
        },
        [],
    );

    const loadAmphoes = useCallback(
        async (parentId: string, preservedId = "", preservedTambonId = "") => {
            amphoeRequest.current?.abort();
            tambonRequest.current?.abort();
            tambonRequest.current = null;
            setTambons([]);
            setTambonLoadState("idle");

            const request = new AbortController();
            amphoeRequest.current = request;
            setAmphoeLoadState("loading");

            try {
                const response = await fetch(
                    `/select/amphoes?province_id=${encodeURIComponent(parentId)}`,
                    {
                        headers: { Accept: "application/json" },
                        signal: request.signal,
                    },
                );
                if (!response.ok) throw new Error("reference request failed");

                const body: unknown = await response.json();
                const items = parseReferenceEnvelope(
                    body,
                    "province",
                    parentId,
                    false,
                );
                if (
                    request.signal.aborted ||
                    amphoeRequest.current !== request
                ) {
                    return;
                }

                setAmphoes(items);
                if (
                    preservedId !== "" &&
                    !items.some((item) => item.id === preservedId)
                ) {
                    setAmphoeId("");
                    setTambonId("");
                } else if (preservedId !== "") {
                    void loadTambons(preservedId, preservedTambonId);
                }
                setAmphoeLoadState("idle");
            } catch {
                if (
                    request.signal.aborted ||
                    amphoeRequest.current !== request
                ) {
                    return;
                }
                tambonRequest.current = null;
                setTambons([]);
                setTambonLoadState("idle");
                setAmphoeLoadState("error");
            }
        },
        [loadTambons],
    );

    useEffect(() => {
        const initialization = window.setTimeout(() => {
            if (address?.provinceId) {
                void loadAmphoes(
                    address.provinceId,
                    address.amphoeId,
                    address.tambonId,
                );
            }
        }, 0);

        return () => {
            window.clearTimeout(initialization);
            amphoeRequest.current?.abort();
            tambonRequest.current?.abort();
        };
    }, [address, loadAmphoes, loadTambons]);

    useEffect(() => {
        if (amphoeLoadState === "error") amphoeErrorRef.current?.focus();
    }, [amphoeLoadState]);

    useEffect(() => {
        if (tambonLoadState === "error") tambonErrorRef.current?.focus();
    }, [tambonLoadState]);

    return (
        <>
            <h2>ข้อมูลส่วนตัวปัจจุบัน</h2>
            <p>
                เลขประจำตัวที่อนุมัติแล้ว:{" "}
                <strong>{profile.identifier.masked}</strong> (อ่านอย่างเดียว)
            </p>
            <form
                className="auth-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    begin();
                    const form = event.currentTarget;
                    void requestJson(
                        "/member/profile",
                        csrfToken,
                        { ...formValues(form), version: profile.version },
                        "PUT",
                    ).then((result) =>
                        finish(
                            result,
                            () => {
                                const value = result.body.profile;
                                if (
                                    typeof value === "object" &&
                                    value !== null
                                ) {
                                    updateProfile(value as Profile);
                                }
                            },
                            form,
                        ),
                    );
                }}
                data-validation-scope="profile"
            >
                <div className="field-grid">
                    <Field
                        id="given_name"
                        label="ชื่อ"
                        defaultValue={profile.givenName}
                        error={fieldError(validation, "profile", "given_name")}
                    />
                    <Field
                        id="family_name"
                        label="นามสกุล"
                        defaultValue={profile.familyName}
                        error={fieldError(validation, "profile", "family_name")}
                    />
                    <Field
                        id="email"
                        label="อีเมลติดต่อ"
                        type="email"
                        defaultValue={profile.contact.email ?? ""}
                        required={false}
                        error={fieldError(validation, "profile", "email")}
                    />
                    <Field
                        id="phone"
                        label="โทรศัพท์"
                        type="tel"
                        defaultValue={profile.contact.phone ?? ""}
                        required={false}
                        error={fieldError(validation, "profile", "phone")}
                    />
                </div>
                <button type="submit" disabled={busy}>
                    บันทึกข้อมูลส่วนตัว
                </button>
            </form>
            <form
                className="auth-form member-subsection"
                onSubmit={(event) => {
                    event.preventDefault();
                    begin();
                    const form = event.currentTarget;
                    const values = formValues(form);
                    void requestJson(
                        "/member/address",
                        csrfToken,
                        {
                            ...values,
                            version: profile.address?.version ?? 0,
                        },
                        "PUT",
                    ).then((result) =>
                        finish(
                            result,
                            () => {
                                const value = result.body.address;
                                if (
                                    typeof value === "object" &&
                                    value !== null
                                ) {
                                    updateProfile({
                                        ...profile,
                                        address: value as Address,
                                    });
                                }
                            },
                            form,
                        ),
                    );
                }}
                data-validation-scope="address"
            >
                <h2>ที่อยู่ในประเทศไทย</h2>
                <Field
                    id="address_line_1"
                    label="ที่อยู่"
                    defaultValue={address?.addressLine1 ?? ""}
                    error={fieldError(validation, "address", "address_line_1")}
                />
                <Field
                    id="address_line_2"
                    label="รายละเอียดเพิ่มเติม"
                    defaultValue={address?.addressLine2 ?? ""}
                    required={false}
                    error={fieldError(validation, "address", "address_line_2")}
                />
                <div className="field-grid">
                    <SelectField
                        id="province_id"
                        label="จังหวัด"
                        items={provinces}
                        value={provinceId}
                        onChange={(value) => {
                            amphoeRequest.current?.abort();
                            tambonRequest.current?.abort();
                            setProvinceId(value);
                            setAmphoeId("");
                            setTambonId("");
                            setAmphoes([]);
                            setTambons([]);
                            setTambonLoadState("idle");
                            if (value === "") {
                                setAmphoeLoadState("idle");
                                return;
                            }
                            void loadAmphoes(value);
                        }}
                        error={fieldError(validation, "address", "province_id")}
                    />
                    <div>
                        <SelectField
                            id="amphoe_id"
                            label="อำเภอ/เขต"
                            items={amphoes}
                            value={amphoeId}
                            preserveUnknownValue
                            disabled={amphoeLoadState !== "idle"}
                            busy={amphoeLoadState === "loading"}
                            statusId={
                                amphoeLoadState === "idle"
                                    ? undefined
                                    : "amphoe-reference-status"
                            }
                            onChange={(value) => {
                                tambonRequest.current?.abort();
                                setAmphoeId(value);
                                setTambonId("");
                                setTambons([]);
                                if (value === "") {
                                    setTambonLoadState("idle");
                                    return;
                                }
                                void loadTambons(value);
                            }}
                            error={fieldError(
                                validation,
                                "address",
                                "amphoe_id",
                            )}
                        />
                        {amphoeLoadState === "loading" && (
                            <p
                                id="amphoe-reference-status"
                                role="status"
                                aria-live="polite"
                            >
                                กำลังโหลดรายการอำเภอ/เขต
                            </p>
                        )}
                        {amphoeLoadState === "error" && (
                            <div
                                id="amphoe-reference-status"
                                className="reference-load-error"
                                role="alert"
                                tabIndex={-1}
                                ref={amphoeErrorRef}
                            >
                                <p>โหลดรายการอำเภอ/เขตไม่สำเร็จ กรุณาลองใหม่</p>
                                <button
                                    type="button"
                                    className="button-secondary"
                                    onClick={() =>
                                        void loadAmphoes(
                                            provinceId,
                                            amphoeId,
                                            tambonId,
                                        )
                                    }
                                >
                                    ลองโหลดอำเภอ/เขตอีกครั้ง
                                </button>
                            </div>
                        )}
                    </div>
                    <div>
                        <SelectField
                            id="tambon_id"
                            label="ตำบล/แขวง"
                            items={tambons}
                            value={tambonId}
                            preserveUnknownValue
                            disabled={tambonLoadState !== "idle"}
                            busy={tambonLoadState === "loading"}
                            statusId={
                                tambonLoadState === "idle"
                                    ? undefined
                                    : "tambon-reference-status"
                            }
                            onChange={setTambonId}
                            error={fieldError(
                                validation,
                                "address",
                                "tambon_id",
                            )}
                        />
                        {tambonLoadState === "loading" && (
                            <p
                                id="tambon-reference-status"
                                role="status"
                                aria-live="polite"
                            >
                                กำลังโหลดรายการตำบล/แขวง
                            </p>
                        )}
                        {tambonLoadState === "error" && (
                            <div
                                id="tambon-reference-status"
                                className="reference-load-error"
                                role="alert"
                                tabIndex={-1}
                                ref={tambonErrorRef}
                            >
                                <p>โหลดรายการตำบล/แขวงไม่สำเร็จ กรุณาลองใหม่</p>
                                <button
                                    type="button"
                                    className="button-secondary"
                                    onClick={() =>
                                        void loadTambons(amphoeId, tambonId)
                                    }
                                >
                                    ลองโหลดตำบล/แขวงอีกครั้ง
                                </button>
                            </div>
                        )}
                    </div>
                </div>
                {address?.postcode && <p>รหัสไปรษณีย์: {address.postcode}</p>}
                <button
                    type="submit"
                    disabled={
                        busy ||
                        amphoeLoadState !== "idle" ||
                        tambonLoadState !== "idle"
                    }
                >
                    บันทึกที่อยู่
                </button>
            </form>
        </>
    );
}

function ApplicationsPanel({ applications }: { applications: Application[] }) {
    return (
        <>
            <h2>ใบสมัครและประวัติ</h2>
            {applications.length === 0 ? (
                <div className="member-empty" role="status">
                    <h3>ยังไม่มีใบสมัคร</h3>
                    <p>เมื่อเริ่มสมัครหลักสูตร รายการจะแสดงที่นี่</p>
                    <a href="/course">ดูหลักสูตร</a>
                </div>
            ) : (
                <ol className="member-list">
                    {applications.map((application) => (
                        <li key={application.courseSessionId}>
                            <strong>
                                รอบ {application.courseSessionId.slice(0, 8)}
                            </strong>
                            <dl>
                                <dt>สถานะปัจจุบัน</dt>
                                <dd>
                                    {applicationStateLabel(application.state)}
                                </dd>
                                <dt>ขั้นตอนถัดไป</dt>
                                <dd>
                                    {application.nextTask ??
                                        application.nextTaskUnavailableReason}
                                </dd>
                                <dt>กำหนดส่ง</dt>
                                <dd>
                                    {application.deadline
                                        ? formatTimelineDate(
                                              application.deadline,
                                          )
                                        : application.deadlineUnavailableReason}
                                </dd>
                                <dt>บันทึกล่าสุด</dt>
                                <dd>
                                    {application.lastSavedAt
                                        ? formatTimelineDate(
                                              application.lastSavedAt,
                                          )
                                        : application.lastSavedAtUnavailableReason}
                                </dd>
                            </dl>
                            <h3>ลำดับเหตุการณ์</h3>
                            <ol aria-label="ลำดับเหตุการณ์ใบสมัคร">
                                {application.history.map((event, index) => (
                                    <li key={`${event.state}-${index}`}>
                                        {applicationStateLabel(event.state)} —{" "}
                                        {event.occurredAt
                                            ? formatTimelineDate(
                                                  event.occurredAt,
                                              )
                                            : event.occurredAtUnavailableReason}
                                    </li>
                                ))}
                            </ol>
                            {safeResumeUrl(application.resumeUrl) ? (
                                <a href={safeResumeUrl(application.resumeUrl)!}>
                                    ทำรายการต่อ
                                </a>
                            ) : (
                                <p>{application.resumeUnavailableReason}</p>
                            )}
                        </li>
                    ))}
                </ol>
            )}
        </>
    );
}

function TrainingPanel({
    training,
    csrfToken,
    busy,
    begin,
    finish,
    validation,
    add,
    replace,
}: {
    training: Training[];
    csrfToken: string;
    busy: boolean;
    begin: () => void;
    finish: (
        result: Awaited<ReturnType<typeof requestJson>>,
        onSuccess: () => void,
        form?: HTMLFormElement,
    ) => void;
    validation: ValidationState;
    add: (training: Training) => void;
    replace: (training: Training) => void;
}) {
    const [editingId, setEditingId] = useState<string | null>(null);
    const pendingAdd = useRef<{
        fingerprint: string;
        idempotencyKey: string;
    } | null>(null);

    return (
        <>
            <h2>ประวัติการอบรม</h2>
            {training.length === 0 ? (
                <p className="member-empty" role="status">
                    ยังไม่มีประวัติการอบรม
                </p>
            ) : (
                <ol className="member-list">
                    {training.map((item) => (
                        <li key={item.id}>
                            {editingId === item.id ? (
                                <form
                                    className="auth-form"
                                    data-validation-scope={`training-edit-${item.id}`}
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        begin();
                                        const form = event.currentTarget;
                                        void requestJson(
                                            `/member/training/${item.id}`,
                                            csrfToken,
                                            {
                                                ...formValues(form),
                                                version: item.version,
                                            },
                                            "PUT",
                                        ).then((result) =>
                                            finish(
                                                result,
                                                () => {
                                                    const value =
                                                        result.body.training;
                                                    if (
                                                        typeof value ===
                                                            "object" &&
                                                        value !== null
                                                    ) {
                                                        replace(
                                                            value as Training,
                                                        );
                                                        setEditingId(null);
                                                    }
                                                },
                                                form,
                                            ),
                                        );
                                    }}
                                >
                                    <h3>แก้ไข {item.courseName}</h3>
                                    <Field
                                        id="course_name"
                                        inputId={`edit-${item.id}-course_name`}
                                        label="ชื่อหลักสูตร"
                                        defaultValue={item.courseName}
                                        error={fieldError(
                                            validation,
                                            `training-edit-${item.id}`,
                                            "course_name",
                                        )}
                                    />
                                    <Field
                                        id="provider_name"
                                        inputId={`edit-${item.id}-provider_name`}
                                        label="หน่วยงาน/ศูนย์"
                                        defaultValue={item.providerName}
                                        error={fieldError(
                                            validation,
                                            `training-edit-${item.id}`,
                                            "provider_name",
                                        )}
                                    />
                                    <div className="field-grid">
                                        <Field
                                            id="started_on"
                                            inputId={`edit-${item.id}-started_on`}
                                            label="วันที่เริ่ม"
                                            type="date"
                                            defaultValue={item.startedOn}
                                            error={fieldError(
                                                validation,
                                                `training-edit-${item.id}`,
                                                "started_on",
                                            )}
                                        />
                                        <Field
                                            id="ended_on"
                                            inputId={`edit-${item.id}-ended_on`}
                                            label="วันที่จบ"
                                            type="date"
                                            defaultValue={item.endedOn ?? ""}
                                            required={false}
                                            error={fieldError(
                                                validation,
                                                `training-edit-${item.id}`,
                                                "ended_on",
                                            )}
                                        />
                                    </div>
                                    <div className="button-row">
                                        <button type="submit" disabled={busy}>
                                            บันทึกการแก้ไข
                                        </button>
                                        <button
                                            type="button"
                                            className="button-secondary"
                                            onClick={() => setEditingId(null)}
                                            disabled={busy}
                                        >
                                            ยกเลิก
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <>
                                    <strong>{item.courseName}</strong>
                                    <span>{item.providerName}</span>
                                    <span>
                                        {item.startedOn}
                                        {item.endedOn
                                            ? ` – ${item.endedOn}`
                                            : ""}
                                    </span>
                                    <button
                                        type="button"
                                        className="button-secondary"
                                        aria-label={`แก้ไข ${item.courseName}`}
                                        onClick={() => setEditingId(item.id)}
                                    >
                                        แก้ไข
                                    </button>
                                </>
                            )}
                        </li>
                    ))}
                </ol>
            )}
            <form
                className="auth-form member-subsection"
                data-validation-scope="training-add"
                onSubmit={(event) => {
                    event.preventDefault();
                    begin();
                    const form = event.currentTarget;
                    const values = formValues(form);
                    const fingerprint = JSON.stringify(values);
                    if (pendingAdd.current?.fingerprint !== fingerprint) {
                        pendingAdd.current = {
                            fingerprint,
                            idempotencyKey: crypto.randomUUID(),
                        };
                    }
                    const idempotencyKey = pendingAdd.current.idempotencyKey;
                    void requestJson(
                        "/member/training",
                        csrfToken,
                        values,
                        "POST",
                        { "Idempotency-Key": idempotencyKey },
                    ).then((result) => {
                        if (result.status !== 0) {
                            pendingAdd.current = null;
                        }
                        finish(
                            result,
                            () => {
                                const value = result.body.training;
                                if (
                                    typeof value === "object" &&
                                    value !== null
                                ) {
                                    add(value as Training);
                                    form.reset();
                                }
                            },
                            form,
                        );
                    });
                }}
            >
                <h3>เพิ่มประวัติ</h3>
                <Field
                    id="course_name"
                    inputId="add-course_name"
                    label="ชื่อหลักสูตร"
                    error={fieldError(
                        validation,
                        "training-add",
                        "course_name",
                    )}
                />
                <Field
                    id="provider_name"
                    inputId="add-provider_name"
                    label="หน่วยงาน/ศูนย์"
                    error={fieldError(
                        validation,
                        "training-add",
                        "provider_name",
                    )}
                />
                <div className="field-grid">
                    <Field
                        id="started_on"
                        inputId="add-started_on"
                        label="วันที่เริ่ม"
                        type="date"
                        error={fieldError(
                            validation,
                            "training-add",
                            "started_on",
                        )}
                    />
                    <Field
                        id="ended_on"
                        inputId="add-ended_on"
                        label="วันที่จบ"
                        type="date"
                        required={false}
                        error={fieldError(
                            validation,
                            "training-add",
                            "ended_on",
                        )}
                    />
                </div>
                <button type="submit" disabled={busy}>
                    เพิ่มประวัติการอบรม
                </button>
            </form>
        </>
    );
}

function PasswordPanel({
    csrfToken,
    busy,
    begin,
    finish,
    validation,
}: {
    csrfToken: string;
    busy: boolean;
    begin: () => void;
    finish: (
        result: Awaited<ReturnType<typeof requestJson>>,
        onSuccess: () => void,
        form?: HTMLFormElement,
    ) => void;
    validation: ValidationState;
}) {
    return (
        <>
            <h2>เปลี่ยนรหัสผ่าน</h2>
            <p>อุปกรณ์อื่นจะถูกออกจากระบบเมื่อเปลี่ยนรหัสผ่านสำเร็จ</p>
            <form
                className="auth-form"
                data-validation-scope="password"
                onSubmit={(event) => {
                    event.preventDefault();
                    begin();
                    const form = event.currentTarget;
                    void submitJson(
                        "/account/password",
                        csrfToken,
                        formValues(form),
                    ).then((result) =>
                        finish(result, () => form.reset(), form),
                    );
                }}
            >
                <Field
                    id="current_password"
                    label="รหัสผ่านปัจจุบัน"
                    type="password"
                    autoComplete="current-password"
                    error={fieldError(
                        validation,
                        "password",
                        "current_password",
                    )}
                />
                <Field
                    id="password"
                    label="รหัสผ่านใหม่"
                    type="password"
                    autoComplete="new-password"
                    minLength={12}
                    error={fieldError(validation, "password", "password")}
                />
                <Field
                    id="password_confirmation"
                    label="ยืนยันรหัสผ่านใหม่"
                    type="password"
                    autoComplete="new-password"
                    minLength={12}
                    error={fieldError(
                        validation,
                        "password",
                        "password_confirmation",
                    )}
                />
                <button type="submit" disabled={busy}>
                    เปลี่ยนรหัสผ่าน
                </button>
            </form>
        </>
    );
}

function Field({
    id,
    inputId = id,
    label,
    type = "text",
    defaultValue = "",
    required = true,
    autoComplete,
    minLength,
    error,
}: {
    id: string;
    inputId?: string;
    label: string;
    type?: string;
    defaultValue?: string;
    required?: boolean;
    autoComplete?: string;
    minLength?: number;
    error?: string;
}) {
    const errorId = `${inputId}-error`;

    return (
        <div className="field">
            <label htmlFor={inputId}>{label}</label>
            <input
                id={inputId}
                name={id}
                type={type}
                defaultValue={defaultValue}
                required={required}
                autoComplete={autoComplete}
                minLength={minLength}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
            />
            {error && (
                <p id={errorId} className="field-error">
                    {error}
                </p>
            )}
        </div>
    );
}

function SelectField({
    id,
    label,
    items,
    value,
    onChange,
    error,
    disabled = false,
    busy = false,
    statusId,
    preserveUnknownValue = false,
}: {
    id: string;
    label: string;
    items: ReferenceItem[];
    value: string;
    onChange?: (value: string) => void;
    error?: string;
    disabled?: boolean;
    busy?: boolean;
    statusId?: string;
    preserveUnknownValue?: boolean;
}) {
    const errorId = `${id}-error`;
    const describedBy = [error ? errorId : undefined, statusId]
        .filter(Boolean)
        .join(" ");
    const hasKnownValue = items.some((item) => item.id === value);

    return (
        <div className="field">
            <label htmlFor={id}>{label}</label>
            <select
                id={id}
                name={id}
                value={value}
                required
                disabled={disabled}
                onChange={(event) => onChange?.(event.currentTarget.value)}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy || undefined}
                aria-busy={busy || undefined}
            >
                <option value="">เลือก{label}</option>
                {preserveUnknownValue && value !== "" && !hasKnownValue && (
                    <option value={value}>ค่าที่บันทึกไว้</option>
                )}
                {items.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.label}
                    </option>
                ))}
            </select>
            {error && (
                <p id={errorId} className="field-error">
                    {error}
                </p>
            )}
        </div>
    );
}

function validationErrors(value: unknown): FieldErrors {
    if (typeof value !== "object" || value === null) return {};

    return Object.fromEntries(
        Object.entries(value)
            .map(([field, messages]) => [
                field,
                Array.isArray(messages) && typeof messages[0] === "string"
                    ? messages[0]
                    : "",
            ])
            .filter((entry) => entry[1] !== ""),
    );
}

function fieldError(
    validation: ValidationState,
    scope: string,
    field: string,
): string | undefined {
    return validation.scope === scope ? validation.errors[field] : undefined;
}

function applicationStateLabel(state: string): string {
    const labels: Record<string, string> = {
        draft: "ฉบับร่าง",
        submitted: "ส่งใบสมัครแล้ว",
        reviewing: "กำลังตรวจสอบ",
        approved: "อนุมัติ",
        rejected: "ไม่ผ่านการพิจารณา",
    };

    return labels[state] ?? "สถานะที่ระบบยังไม่รองรับ";
}

function formatTimelineDate(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "เวลาไม่พร้อมใช้งาน";

    return new Intl.DateTimeFormat("th-TH", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: "Asia/Bangkok",
    }).format(date);
}

function safeResumeUrl(value: string | null): string | null {
    return value?.startsWith("/member/applications/") ? value : null;
}
