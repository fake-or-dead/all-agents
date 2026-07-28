import { Head } from "@inertiajs/react";
import { useCallback, useEffect, useState } from "react";

type CheckState = "ok" | "failed" | "stale";

type Readiness = {
    status: "ready" | "degraded";
    checks: {
        database: CheckState;
        redis: CheckState;
        queue: CheckState;
        scheduler: CheckState;
        migrations: {
            status: "ok" | "pending";
            pending: number;
        };
    };
};

type Props = {
    build: {
        version: string;
        commit: string;
    };
};

const checkLabels: Record<string, string> = {
    database: "ฐานข้อมูล",
    redis: "Redis",
    queue: "คิวงาน",
    scheduler: "ตัวจัดตารางงาน",
    migrations: "โครงสร้างฐานข้อมูล",
};

const stateLabels: Record<CheckState | "pending", string> = {
    ok: "พร้อมใช้งาน",
    failed: "เชื่อมต่อไม่ได้",
    stale: "ไม่ได้รับสัญญาณล่าสุด",
    pending: "รออัปเดต",
};

export default function SystemState({ build }: Props) {
    const [readiness, setReadiness] = useState<Readiness | null>(null);
    const [loading, setLoading] = useState(true);

    const refresh = useCallback(async () => {
        setLoading(true);

        try {
            const response = await fetch("/health/ready", {
                headers: { Accept: "application/json" },
            });
            const body = (await response.json()) as Readiness;
            setReadiness(body);
        } catch {
            setReadiness(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        let active = true;

        void fetch("/health/ready", {
            headers: { Accept: "application/json" },
        })
            .then(async (response) => (await response.json()) as Readiness)
            .then((body) => {
                if (active) {
                    setReadiness(body);
                }
            })
            .catch(() => {
                if (active) {
                    setReadiness(null);
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    const checks: Array<[keyof typeof checkLabels, CheckState | "pending"]> =
        readiness
            ? [
                  ["database", readiness.checks.database],
                  ["redis", readiness.checks.redis],
                  ["queue", readiness.checks.queue],
                  ["scheduler", readiness.checks.scheduler],
                  ["migrations", readiness.checks.migrations.status],
              ]
            : [];

    return (
        <>
            <Head title="สถานะระบบ" />
            <a className="skip-link" href="#main-content">
                ข้ามไปยังเนื้อหาหลัก
            </a>
            <header className="site-header">
                <div className="header-inner">
                    <span className="wordmark">Tapoda Next</span>
                    <span className="build">
                        รุ่น {build.version} · {build.commit}
                    </span>
                </div>
            </header>
            <main id="main-content" className="page-shell" tabIndex={-1}>
                <section className="hero" aria-labelledby="page-title">
                    <p className="eyebrow">ระบบดูแลวงจรหลักสูตรธรรมะ</p>
                    <h1 id="page-title">สถานะระบบ</h1>
                    <p className="lede">
                        หน้านี้แสดงเฉพาะข้อมูลความพร้อมของระบบ
                        โดยไม่เปิดเผยข้อมูลส่วนบุคคลหรือรายละเอียดการเชื่อมต่อ
                    </p>
                </section>

                <section
                    aria-labelledby="readiness-title"
                    className="status-panel"
                >
                    <div className="panel-heading">
                        <div>
                            <p className="eyebrow">ตรวจสอบล่าสุด</p>
                            <h2 id="readiness-title">
                                {loading
                                    ? "กำลังตรวจสอบ"
                                    : readiness?.status === "ready"
                                      ? "ระบบพร้อมใช้งาน"
                                      : "ระบบยังไม่พร้อม"}
                            </h2>
                        </div>
                        <button
                            type="button"
                            onClick={() => void refresh()}
                            disabled={loading}
                        >
                            {loading ? "กำลังตรวจสอบ…" : "ตรวจสอบอีกครั้ง"}
                        </button>
                    </div>

                    <p className="sr-only" role="status" aria-live="polite">
                        {loading
                            ? "กำลังตรวจสอบสถานะระบบ"
                            : readiness?.status === "ready"
                              ? "ระบบพร้อมใช้งาน"
                              : "ระบบยังไม่พร้อมใช้งาน"}
                    </p>

                    {readiness ? (
                        <ul className="check-grid">
                            {checks.map(([name, state]) => (
                                <li key={name} className="check-card">
                                    <span>{checkLabels[name]}</span>
                                    <strong data-state={state}>
                                        {stateLabels[state]}
                                    </strong>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        !loading && (
                            <p className="notice" role="alert">
                                ไม่สามารถอ่านสถานะได้ในขณะนี้ กรุณาลองอีกครั้ง
                            </p>
                        )
                    )}
                </section>

                <p className="health-note">
                    ระบบเฝ้าระวังใช้ <code>/health/live</code> และ{" "}
                    <code>/health/ready</code>
                </p>
            </main>
        </>
    );
}
