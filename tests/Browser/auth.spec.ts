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

async function expectFullDocumentAxe(page: Page) {
    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);
}

async function expectAuthReflow(page: Page) {
    await page.setViewportSize({ width: 320, height: 900 });
    await expectNoHorizontalOverflow(page);
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.evaluate(() => {
        document.documentElement.style.zoom = "2";
    });
    await expectNoHorizontalOverflow(page);
    await page.evaluate(() => {
        document.documentElement.style.zoom = "";
    });
}

async function keyboardFill(
    target: ReturnType<Page["locator"]>,
    value: string,
) {
    await target.pressSequentially(value);
}

async function keyboardActivate(target: ReturnType<Page["locator"]>) {
    await target.press("Enter");
}

function recoveryPathFor(email: string): string {
    const container = process.env.PLAYWRIGHT_RECOVERY_CONTAINER;
    const command = container
        ? [
              "exec",
              "-i",
              container,
              "php",
              "artisan",
              "identity:local-recovery-path",
          ]
        : [
              "compose",
              "exec",
              "-T",
              "web",
              "php",
              "artisan",
              "identity:local-recovery-path",
          ];

    return execFileSync("docker", command, {
        cwd: process.cwd(),
        encoding: "utf8",
        input: `${email}\n`,
    }).trim();
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
    await expectAuthReflow(page);
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
    await expectFullDocumentAxe(page);

    await page.getByLabel("อีเมล").fill(`stale-${unique}@example.test`);
    await page.getByRole("button", { name: "ขอรหัส" }).click();
    await page.getByLabel("รหัสยืนยัน 6 หลัก").fill("246810");
    await page.getByRole("button", { name: "ยืนยันรหัส" }).click();
    await expect(page.locator("fieldset")).toBeEnabled();
    await page.getByLabel("อีเมล").fill(`changed-${unique}@example.test`);
    await expect(page.locator("fieldset")).toHaveAttribute("disabled", "");

    await registerAccount(page, unique);
    await expectFullDocumentAxe(page);
    await page.getByRole("button", { name: "ออกจากระบบ" }).click();
    await expect(page).toHaveURL(/\/signin$/);

    await expectAuthReflow(page);
});

test("FLOW-AUTH-01 verified proof expires while filling the form and can be re-verified without reload", async ({
    page,
}, testInfo) => {
    const unique = `${Date.now()}${testInfo.workerIndex}expiry`;
    const email = `expiry-${unique}@example.test`;
    let verificationCount = 0;

    await page.route("**/auth/verification/verify", async (route) => {
        verificationCount += 1;
        await route.fulfill({
            status: 200,
            contentType: "application/json",
            body: JSON.stringify({
                registration_token: `proof-${verificationCount}`,
                expires_at: new Date(Date.now() + 500).toISOString(),
            }),
        });
    });

    await page.goto("/signup");
    await page.getByLabel("อีเมล").fill(email);
    await page.getByRole("button", { name: "ขอรหัส" }).click();
    await page.getByLabel("รหัสยืนยัน 6 หลัก").fill("246810");
    await page.getByRole("button", { name: "ยืนยันรหัส" }).click();
    await expect(page.locator("fieldset")).toBeEnabled();
    await page.getByLabel("เลขเอกสารประจำตัว").fill(`EXP${unique}`);

    await expect(page.getByRole("status")).toContainText("หมดอายุ");
    await expect(page.locator("fieldset")).toHaveAttribute("disabled", "");
    await expect(page.getByRole("button", { name: "ขอรหัส" })).toBeFocused();
    await expectAuthReflow(page);
    await expectFullDocumentAxe(page);

    await page.getByRole("button", { name: "ขอรหัส" }).click();
    await page.getByLabel("รหัสยืนยัน 6 หลัก").fill("246810");
    await page.getByRole("button", { name: "ยืนยันรหัส" }).click();
    await expect(page.locator("fieldset")).toBeEnabled();
    await expectFullDocumentAxe(page);
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
    await expect(page.getByRole("alert")).toBeFocused();
    await expect(page.getByLabel("รหัสผ่าน")).toHaveAttribute(
        "aria-describedby",
        "signin-error",
    );
    await expect(
        page.getByRole("button", { name: "เข้าสู่ระบบ" }),
    ).toBeEnabled();
    await page.unroute("**/signin");

    await page.getByLabel("รหัสผ่าน").fill("wrong-password-123");
    await page.route("**/signin", async (route) => {
        if (route.request().method() === "POST") {
            await route.fulfill({
                status: 429,
                contentType: "application/json",
                body: JSON.stringify({
                    message: "ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่ภายหลัง",
                }),
            });
            return;
        }
        await route.continue();
    });
    await page.getByRole("button", { name: "เข้าสู่ระบบ" }).click();
    await expect(page.getByRole("alert")).toContainText(
        "ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่ภายหลัง",
    );
    await expect(page.getByRole("alert")).toBeFocused();
    await expectAuthReflow(page);
    await expectFullDocumentAxe(page);
    await page.unroute("**/signin");

    await page.getByLabel("รหัสผ่าน").fill("wrong-password-123");
    await page.getByRole("button", { name: "เข้าสู่ระบบ" }).click();
    await expect(page.getByRole("alert")).toContainText(
        "ข้อมูลเข้าสู่ระบบไม่ถูกต้อง",
    );

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
    await expectAuthReflow(page);
    await expectFullDocumentAxe(page);
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
    await expect(page.getByRole("alert")).toBeFocused();
    await expect(page.getByLabel("อีเมล")).toHaveAttribute(
        "aria-describedby",
        "forgot-error",
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
    await expectAuthReflow(page);
    await expectFullDocumentAxe(page);
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
    await expect(page.getByRole("alert")).toBeFocused();
    await expectAuthReflow(page);
    await expectFullDocumentAxe(page);

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

test("FLOW-AUTH-04 keyboard-only registration, sign-in, recovery, and password change", async ({
    page,
}, testInfo) => {
    const unique = `${Date.now()}${testInfo.workerIndex}keyboard`;
    const email = `keyboard-${unique}@example.test`;
    const identity = `K${Date.now()}${testInfo.workerIndex}`;

    await page.goto("/signup");
    await keyboardFill(page.getByLabel("อีเมล"), email);
    await keyboardActivate(page.getByRole("button", { name: "ขอรหัส" }));
    await expect(page.getByLabel("รหัสยืนยัน 6 หลัก")).toBeFocused();
    await page.keyboard.insertText("246810");
    await keyboardActivate(page.getByRole("button", { name: "ยืนยันรหัส" }));
    await expect(page.locator("fieldset")).toBeEnabled();
    await page.getByLabel("ประเภทเอกสารประจำตัว").selectOption("passport");
    await keyboardFill(page.getByLabel("เลขเอกสารประจำตัว"), identity);
    await keyboardFill(page.getByLabel("ชื่อ"), "ทดสอบ");
    await keyboardFill(page.getByLabel("นามสกุล"), "คีย์บอร์ด");
    await keyboardFill(page.getByLabel(/^รหัสผ่านใหม่/), password);
    await keyboardFill(page.getByLabel("ยืนยันรหัสผ่านใหม่"), password);
    await page.getByRole("checkbox").press("Space");
    await keyboardActivate(page.getByRole("button", { name: "สร้างบัญชี" }));
    await expect(page).toHaveURL(/\/account$/);
    await expectAuthReflow(page);
    await expectFullDocumentAxe(page);

    await page.getByRole("button", { name: "ออกจากระบบ" }).click();
    await expect(page).toHaveURL(/\/signin$/);
    await page.getByLabel("ประเภทเอกสารประจำตัว").selectOption("passport");
    await keyboardFill(page.getByLabel("เลขเอกสารประจำตัว"), identity);
    await keyboardFill(page.getByLabel("รหัสผ่าน"), password);
    await keyboardActivate(page.getByRole("button", { name: "เข้าสู่ระบบ" }));
    await expect(page).toHaveURL(/\/account$/);

    await keyboardFill(page.getByLabel("รหัสผ่านปัจจุบัน"), password);
    await keyboardFill(
        page.getByLabel("รหัสผ่านใหม่", { exact: true }),
        "changed-password-456",
    );
    await keyboardFill(
        page.getByLabel("ยืนยันรหัสผ่านใหม่"),
        "changed-password-456",
    );
    await keyboardActivate(
        page.getByRole("button", { name: "เปลี่ยนรหัสผ่าน" }),
    );
    await expect(page.getByRole("status")).toContainText("เปลี่ยนรหัสผ่าน");
    await expectAuthReflow(page);
    await expectFullDocumentAxe(page);

    await page.route("**/account/password", async (route) => {
        if (route.request().method() === "POST") {
            await route.fulfill({
                status: 401,
                contentType: "application/json",
                body: JSON.stringify({
                    message: "เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่",
                }),
            });
            return;
        }
        await route.continue();
    });
    await page.getByLabel("รหัสผ่านปัจจุบัน").fill("changed-password-456");
    await page
        .getByLabel("รหัสผ่านใหม่", { exact: true })
        .fill("denied-password-789");
    await page.getByLabel("ยืนยันรหัสผ่านใหม่").fill("denied-password-789");
    await page.getByRole("button", { name: "เปลี่ยนรหัสผ่าน" }).click();
    await expect(page.getByRole("alert")).toContainText("เซสชันหมดอายุ");
    await expect(page.getByRole("alert")).toBeFocused();
    await expect(page.getByLabel("รหัสผ่านปัจจุบัน")).toHaveAttribute(
        "aria-describedby",
        "account-error",
    );
    await expectAuthReflow(page);
    await expectFullDocumentAxe(page);
    await page.unroute("**/account/password");

    await page.getByRole("button", { name: "ออกจากระบบ" }).click();
    await page.goto("/forgot");
    await keyboardFill(page.getByLabel("อีเมล"), email);
    await keyboardActivate(page.getByRole("button", { name: "ขอลิงก์กู้คืน" }));
    await expect(page.getByRole("status")).toContainText("หากมีบัญชีที่ตรงกัน");
    const recoveryPath = recoveryPathFor(email);
    await page.goto(recoveryPath);
    await keyboardFill(
        page.getByLabel("รหัสผ่านใหม่", { exact: true }),
        "recovered-keyboard-789",
    );
    await keyboardFill(
        page.getByLabel("ยืนยันรหัสผ่านใหม่"),
        "recovered-keyboard-789",
    );
    await keyboardActivate(
        page.getByRole("button", { name: "ตั้งรหัสผ่านใหม่" }),
    );
    await expect(page).toHaveURL(/\/signin$/);
    await expectAuthReflow(page);
    await expectFullDocumentAxe(page);
});
