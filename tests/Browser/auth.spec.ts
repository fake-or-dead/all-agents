import AxeBuilder from "@axe-core/playwright";
import { expect, type Page, test } from "@playwright/test";
import { execFileSync } from "node:child_process";

const password = "browser-password-123";

async function registerAccount(
    page: Page,
    unique: string,
): Promise<{ email: string; identity: string }> {
    const email = `browser-auth-${unique}@example.test`;
    const identity = `E2E${unique}`;

    await page.goto("/signup");
    await page.getByLabel("อีเมล").fill(email);
    await page.getByRole("button", { name: "ขอรหัส" }).click();
    await expect(page.getByRole("status")).toContainText("หากข้อมูลถูกต้อง");
    await page.getByLabel("รหัสยืนยัน 6 หลัก").fill("246810");
    await page.getByRole("button", { name: "ยืนยันรหัส" }).click();
    await expect(page.getByRole("status")).toContainText("ยืนยันอีเมลแล้ว");
    await page.getByLabel("ประเภทเอกสารประจำตัว").selectOption("passport");
    await page.getByLabel("เลขเอกสารประจำตัว").fill(identity);
    await page.getByLabel("ชื่อ").fill("ทดสอบ");
    await page.getByLabel("นามสกุล").fill("เบราว์เซอร์");
    await page.getByLabel(/^รหัสผ่านใหม่/).fill(password);
    await page.getByLabel("ยืนยันรหัสผ่านใหม่").fill(password);
    await page.getByRole("checkbox").check();
    await page.getByRole("button", { name: "สร้างบัญชี" }).click();
    await expect(page).toHaveURL(/\/account$/);

    return { email, identity };
}

async function expectNoHorizontalOverflow(page: Page) {
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth <=
                document.documentElement.clientWidth,
        ),
    ).toBe(true);
}

function recoveryPathFor(email: string): string {
    return execFileSync(
        "docker",
        [
            "compose",
            "exec",
            "-T",
            "web",
            "php",
            "artisan",
            "identity:local-recovery-path",
        ],
        {
            cwd: process.cwd(),
            encoding: "utf8",
            input: `${email}\n`,
        },
    ).trim();
}

test("FLOW-AUTH-01 registration is Thai-first, accessible, responsive, and signs in", async ({
    page,
}, testInfo) => {
    const unique = `${Date.now()}${testInfo.workerIndex}1`;

    await page.setViewportSize({ width: 320, height: 900 });
    await page.goto("/signup");
    await expect(
        page.getByRole("heading", { name: "สร้างบัญชี", level: 1 }),
    ).toBeVisible();
    await expect(page.locator("html")).toHaveAttribute("lang", "th");
    await expectNoHorizontalOverflow(page);
    await expect(
        page.getByRole("heading", {
            name: "ความยินยอมการสร้างบัญชี (ตัวอย่างภายใน)",
            level: 2,
        }),
    ).toBeVisible();
    await expect(
        page
            .getByRole("region", {
                name: "ความยินยอมการสร้างบัญชี (ตัวอย่างภายใน)",
            })
            .getByText("local-fixture-v1"),
    ).toBeVisible();
    expect(
        await new AxeBuilder({ page }).include("main").analyze(),
    ).toMatchObject({ violations: [] });

    await page.getByLabel("อีเมล").fill(`stale-${unique}@example.test`);
    await page.getByRole("button", { name: "ขอรหัส" }).click();
    await page.getByLabel("รหัสยืนยัน 6 หลัก").fill("246810");
    await page.getByRole("button", { name: "ยืนยันรหัส" }).click();
    await expect(page.locator("fieldset")).toBeEnabled();
    await page.getByLabel("อีเมล").fill(`changed-${unique}@example.test`);
    await expect(page.locator("fieldset")).toHaveAttribute("disabled", "");

    await registerAccount(page, unique);
    expect(
        await new AxeBuilder({ page }).include("main").analyze(),
    ).toMatchObject({ violations: [] });
    await page.getByRole("button", { name: "ออกจากระบบ" }).click();
    await expect(page).toHaveURL(/\/signin$/);
});

test("FLOW-AUTH-02 sign-in handles failures, keyboard, zoom, and restores access", async ({
    page,
}, testInfo) => {
    const account = await registerAccount(
        page,
        `${Date.now()}${testInfo.workerIndex}2`,
    );
    await page.getByRole("button", { name: "ออกจากระบบ" }).click();
    await expect(page).toHaveURL(/\/signin$/);

    await page.evaluate(() => {
        document.documentElement.style.zoom = "2";
    });
    await expectNoHorizontalOverflow(page);
    await page.keyboard.press("Tab");
    await expect(page.locator(".skip-link")).toBeFocused();

    await page.getByLabel("ประเภทเอกสารประจำตัว").selectOption("passport");
    await page.getByLabel("เลขเอกสารประจำตัว").fill(account.identity);
    await page.getByLabel("รหัสผ่าน").fill(password);
    await page.route("**/signin", async (route) => {
        if (route.request().method() === "POST") {
            await route.abort("connectionfailed");
            return;
        }
        await route.continue();
    });
    await page.getByRole("button", { name: "เข้าสู่ระบบ" }).click();
    await expect(page.getByRole("alert")).toContainText("เชื่อมต่อระบบไม่ได้");
    await expect(
        page.getByRole("button", { name: "เข้าสู่ระบบ" }),
    ).toBeEnabled();
    await page.unroute("**/signin");

    await page.getByLabel("รหัสผ่าน").fill("wrong-password-123");
    await page.getByRole("button", { name: "เข้าสู่ระบบ" }).click();
    await expect(page.getByRole("alert")).toContainText(
        "ข้อมูลเข้าสู่ระบบไม่ถูกต้อง",
    );
    expect(
        await new AxeBuilder({ page }).include("main").analyze(),
    ).toMatchObject({ violations: [] });

    await page.getByLabel("รหัสผ่าน").fill(password);
    await page.getByRole("button", { name: "เข้าสู่ระบบ" }).click();
    await expect(page).toHaveURL(/\/account$/);
});

test("FLOW-AUTH-03 recovery is neutral, one-use, and revokes old credentials", async ({
    page,
}, testInfo) => {
    const account = await registerAccount(
        page,
        `${Date.now()}${testInfo.workerIndex}3`,
    );
    await page.getByRole("button", { name: "ออกจากระบบ" }).click();
    await expect(page).toHaveURL(/\/signin$/);
    await page.waitForLoadState("networkidle");

    await page.goto("/forgot");
    await expectNoHorizontalOverflow(page);
    expect(
        await new AxeBuilder({ page }).include("main").analyze(),
    ).toMatchObject({ violations: [] });
    await page.route("**/forgot", async (route) => {
        if (route.request().method() === "POST") {
            await route.fulfill({
                status: 500,
                contentType: "text/html",
                body: "<h1>temporary failure</h1>",
            });
            return;
        }
        await route.continue();
    });
    await page.getByLabel("อีเมล").fill(account.email);
    await page.getByRole("button", { name: "ขอลิงก์กู้คืน" }).click();
    await expect(page.getByRole("alert")).toContainText(
        "ระบบตอบกลับไม่สมบูรณ์",
    );
    await expect(
        page.getByRole("button", { name: "ขอลิงก์กู้คืน" }),
    ).toBeEnabled();
    await page.unroute("**/forgot");

    await page.getByRole("button", { name: "ขอลิงก์กู้คืน" }).click();
    await expect(page.getByRole("status")).toHaveText(
        "หากมีบัญชีที่ตรงกัน ระบบได้ส่งวิธีกู้คืนให้แล้ว",
    );
    const path = recoveryPathFor(account.email);

    await page.goto(path);
    expect(
        await new AxeBuilder({ page }).include("main").analyze(),
    ).toMatchObject({ violations: [] });
    await page
        .getByLabel("รหัสผ่านใหม่", { exact: true })
        .fill("recovered-password-456");
    await page.getByLabel("ยืนยันรหัสผ่านใหม่").fill("recovered-password-456");
    await page.getByRole("button", { name: "ตั้งรหัสผ่านใหม่" }).click();
    await expect(page).toHaveURL(/\/signin$/);

    await page.goto(path);
    await page
        .getByLabel("รหัสผ่านใหม่", { exact: true })
        .fill("replayed-password-789");
    await page.getByLabel("ยืนยันรหัสผ่านใหม่").fill("replayed-password-789");
    await page.getByRole("button", { name: "ตั้งรหัสผ่านใหม่" }).click();
    await expect(page.getByRole("alert")).toContainText(
        "ลิงก์กู้คืนไม่ถูกต้อง",
    );

    await page.goto("/signin");
    await page.getByLabel("ประเภทเอกสารประจำตัว").selectOption("passport");
    await page.getByLabel("เลขเอกสารประจำตัว").fill(account.identity);
    await page.getByLabel("รหัสผ่าน").fill("recovered-password-456");
    await page.getByRole("button", { name: "เข้าสู่ระบบ" }).click();
    await expect(page).toHaveURL(/\/account$/);

    const response = await page.request.post("/auth/verification/request", {
        headers: {
            Accept: "application/json",
            Origin: "https://attacker.invalid",
            "Sec-Fetch-Site": "cross-site",
        },
        data: { email: "csrf-check@example.test" },
    });
    expect([403, 419]).toContain(response.status());
});
