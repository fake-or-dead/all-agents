import { Head } from "@inertiajs/react";
import type { ReactNode } from "react";

type Props = {
    title: string;
    eyebrow: string;
    children: ReactNode;
};

export default function AuthShell({ title, eyebrow, children }: Props) {
    return (
        <>
            <Head title={title} />
            <a className="skip-link" href="#main-content">
                ข้ามไปยังเนื้อหาหลัก
            </a>
            <header className="site-header">
                <div className="header-inner">
                    <a className="wordmark" href="/">
                        Tapoda Next
                    </a>
                    <nav aria-label="บัญชี">
                        <a href="/signin">เข้าสู่ระบบ</a>
                    </nav>
                </div>
            </header>
            <main id="main-content" className="auth-shell" tabIndex={-1}>
                <section className="auth-card" aria-labelledby="page-title">
                    <p className="eyebrow">{eyebrow}</p>
                    <h1 id="page-title">{title}</h1>
                    {children}
                </section>
            </main>
        </>
    );
}
