import AxeBuilder from "@axe-core/playwright";
import { expect, type Page, test } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { randomUUID } from "node:crypto";

const originalPassword = "browser-password-123";
const replacementPassword = "member-replacement-456";
const trainingOperationStorageKey =
    "tapoda.member.training-add.idempotency-key.v1";

test.describe.configure({ mode: "serial" });
test.beforeEach(async ({ context }) => {
    await context.setExtraHTTPHeaders({
        "X-Tapoda-Test-Client": `member-${randomUUID()}`,
    });
});

test.beforeAll(() => {
    const container = process.env.PLAYWRIGHT_MEMBER_CONTAINER;
    if (!container) return;

    execFileSync(
        "docker",
        [
            "exec",
            container,
            "php",
            "artisan",
            "db:seed",
            "--class=Database\\Seeders\\MemberBrowserFixtureSeeder",
            "--force",
        ],
        { cwd: process.cwd(), encoding: "utf8" },
    );
});

async function registerMember(page: Page, unique: string) {
    const identity = `MC${unique.replace(/\D/g, "").slice(-16)}`;
    await page.goto("/signup");
    await page
        .getByLabel("อีเมล")
        .fill(`member-${unique}@member-browser.example.test`);
    const verificationRequest = page.waitForResponse(
        (response) =>
            response.url().endsWith("/auth/verification/request") &&
            response.request().method() === "POST",
    );
    await page.getByRole("button", { name: "ขอรหัส" }).click();
    expect((await verificationRequest).status()).toBe(202);
    await page.getByLabel("รหัสยืนยัน 6 หลัก").fill("246810");
    const verificationResponse = page.waitForResponse(
        (response) =>
            response.url().endsWith("/auth/verification/verify") &&
            response.request().method() === "POST",
    );
    await page.getByRole("button", { name: "ยืนยันรหัส" }).click();
    expect((await verificationResponse).status()).toBe(200);
    await page.getByLabel("ประเภทเอกสารประจำตัว").selectOption("passport");
    await page.getByLabel("เลขเอกสารประจำตัว").fill(identity);
    await page.getByLabel("ชื่อ", { exact: true }).fill("สมาชิก");
    await page.getByLabel("นามสกุล").fill("ทดสอบ");
    await page.getByLabel(/^รหัสผ่านใหม่/).fill(originalPassword);
    await page.getByLabel("ยืนยันรหัสผ่านใหม่").fill(originalPassword);
    await page.getByRole("checkbox").check();
    const registrationResponse = page.waitForResponse(
        (response) =>
            response.url().endsWith("/signup") &&
            response.request().method() === "POST",
    );
    await page.getByRole("button", { name: "สร้างบัญชี" }).click();
    expect((await registrationResponse).status()).toBe(201);
    await expect(page).toHaveURL(/\/account$/, { timeout: 15_000 });

    return identity;
}

async function expectNoOverflow(page: Page) {
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth <=
                document.documentElement.clientWidth,
        ),
    ).toBe(true);
}

function successfulTrainingAuditCount(): number {
    const container = process.env.PLAYWRIGHT_MEMBER_CONTAINER;
    const php = [
        "require 'vendor/autoload.php';",
        "$app = require 'bootstrap/app.php';",
        "$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();",
        "echo Illuminate\\Support\\Facades\\DB::table('audit_events')",
        "->where('action', 'people.training.added')",
        "->where('outcome', 'succeeded')->count();",
    ].join("");
    const command = container
        ? ["exec", container, "php", "-r", php]
        : ["compose", "exec", "-T", "web", "php", "-r", php];

    return Number(
        execFileSync("docker", command, {
            cwd: process.cwd(),
            encoding: "utf8",
        }).trim(),
    );
}

test("FLOW-MEMBER-01 profile, Thai address, training, and deep links are owned and accessible", async ({
    page,
}, testInfo) => {
    await registerMember(page, `${Date.now()}${testInfo.workerIndex}`);
    const profileResponse = await page.goto("/member/profile");
    expect(profileResponse?.status()).toBe(200);
    await expect(
        page.getByRole("heading", { name: "ข้อมูลสมาชิก", level: 1 }),
    ).toBeVisible();
    await expect(page.getByText(/••••/)).toBeVisible();
    await expect(page.getByText("อ่านอย่างเดียว")).toBeVisible();

    for (const width of [320, 375, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await expectNoOverflow(page);
    }
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);

    await page.keyboard.press("Tab");
    await expect(page.locator(".skip-link")).toBeFocused();
    await page.keyboard.press("Enter");
    await expect(page.locator("#member-panel")).toBeFocused();

    await page.getByLabel("ชื่อ", { exact: true }).fill("สมาชิกแก้ไข");
    await page.getByLabel("อีเมลติดต่อ").fill("member-current@example.test");
    const phone = page.getByLabel("โทรศัพท์");
    await phone.fill("1");
    const validationResponse = page.waitForResponse(
        (response) =>
            response.url().endsWith("/member/profile") &&
            response.request().method() === "PUT" &&
            response.status() === 422,
    );
    await page.getByRole("button", { name: "บันทึกข้อมูลส่วนตัว" }).click();
    await validationResponse;
    await expect(page.getByRole("alert")).toContainText(
        "รูปแบบ โทรศัพท์ ไม่ถูกต้อง",
    );
    await expect(page.locator("#phone-error")).toHaveText(
        "รูปแบบ โทรศัพท์ ไม่ถูกต้อง",
    );
    await expect(phone).toHaveValue("1");
    await expect(phone).toBeFocused();

    await phone.fill("0812345678");
    await page.getByRole("button", { name: "บันทึกข้อมูลส่วนตัว" }).click();
    await expect(page.locator(".form-status")).toContainText(
        "บันทึกข้อมูลแล้ว",
    );

    await page.getByLabel("ที่อยู่").fill("99 ถนนทดสอบ");
    await expect(
        page.getByLabel("จังหวัด").locator('option[value="member-bkk"]'),
    ).toHaveCount(1);
    const amphoeResponsePromise = page.waitForResponse(
        (response) =>
            response.url().includes("/select/amphoes?province_id=member-bkk") &&
            response.request().method() === "GET",
    );
    await page.getByLabel("จังหวัด").selectOption("member-bkk");
    const amphoeResponse = await amphoeResponsePromise;
    expect(amphoeResponse.status()).toBe(200);
    await expect(
        page.getByLabel("อำเภอ/เขต").locator('option[value="member-phra"]'),
    ).toHaveCount(1);
    const tambonResponsePromise = page.waitForResponse(
        (response) =>
            response.url().includes("/select/tambons?amphoe_id=member-phra") &&
            response.request().method() === "GET",
    );
    await page.getByLabel("อำเภอ/เขต").selectOption("member-phra");
    const tambonResponse = await tambonResponsePromise;
    expect(tambonResponse.status()).toBe(200);
    await expect(
        page.getByLabel("ตำบล/แขวง").locator('option[value="member-wat"]'),
    ).toHaveCount(1);
    await page.getByLabel("ตำบล/แขวง").selectOption("member-wat");
    await page.getByRole("button", { name: "บันทึกที่อยู่" }).click();
    await expect(page.locator(".form-status")).toContainText(
        "บันทึกข้อมูลแล้ว",
    );
    await expect(page.getByText("รหัสไปรษณีย์: 10200")).toBeVisible();

    await page.getByRole("link", { name: "ประวัติการอบรม" }).click();
    await expect(page).toHaveURL(/\/member\/training$/);
    await expect(page.getByText("ยังไม่มีประวัติการอบรม")).toBeVisible();
    await page.getByLabel("ชื่อหลักสูตร").fill("อบรมสติ");
    await page.getByLabel("หน่วยงาน/ศูนย์").fill("ศูนย์ทดสอบ");
    await page.getByLabel("วันที่เริ่ม").fill("2026-02-10");
    await page.getByLabel("วันที่จบ").fill("2026-02-12");
    const auditCountBeforeLostResponse = successfulTrainingAuditCount();
    let addTrainingRequests = 0;
    const addTrainingKeys: string[] = [];
    await page.route("**/member/training", async (route) => {
        if (route.request().method() !== "POST") {
            await route.continue();
            return;
        }
        addTrainingRequests += 1;
        addTrainingKeys.push(
            route.request().headers()["idempotency-key"] ?? "",
        );
        if (addTrainingRequests === 1) {
            const committed = await route.fetch();
            expect(committed.status()).toBe(201);
            await route.abort("connectionfailed");
            return;
        }
        await route.continue();
    });
    await page.getByRole("button", { name: "เพิ่มประวัติการอบรม" }).click();
    await expect(page.getByRole("alert")).toContainText("เชื่อมต่อระบบไม่ได้");
    await expect(page.getByRole("button", { name: "โหลดใหม่" })).toBeVisible();
    const storageAfterLostResponse = await page.evaluate(
        (operationKey) => ({
            local: Object.fromEntries(Object.entries(localStorage)),
            session: Object.fromEntries(Object.entries(sessionStorage)),
            operation: sessionStorage.getItem(operationKey),
        }),
        trainingOperationStorageKey,
    );
    expect(storageAfterLostResponse.operation).toBe(addTrainingKeys[0]);
    expect(storageAfterLostResponse.operation).toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    );
    for (const privateValue of [
        "อบรมสติ",
        "ศูนย์ทดสอบ",
        "2026-02-10",
        "2026-02-12",
    ]) {
        expect(JSON.stringify(storageAfterLostResponse)).not.toContain(
            privateValue,
        );
    }
    await Promise.all([
        page.waitForNavigation(),
        page.getByRole("button", { name: "โหลดใหม่" }).click(),
    ]);
    await expect(page.getByText("อบรมสติ")).toHaveCount(1);
    expect(
        await page.evaluate(
            (operationKey) => sessionStorage.getItem(operationKey),
            trainingOperationStorageKey,
        ),
    ).toBe(addTrainingKeys[0]);
    await page.reload();
    await expect(page.getByText("อบรมสติ")).toHaveCount(1);
    expect(
        await page.evaluate(
            (operationKey) => sessionStorage.getItem(operationKey),
            trainingOperationStorageKey,
        ),
    ).toBe(addTrainingKeys[0]);
    await page.getByLabel("ชื่อหลักสูตร").fill("อบรมสติ");
    await page.getByLabel("หน่วยงาน/ศูนย์").fill("ศูนย์ทดสอบ");
    await page.getByLabel("วันที่เริ่ม").fill("2026-02-10");
    await page.getByLabel("วันที่จบ").fill("2026-02-12");
    const replayResponsePromise = page.waitForResponse(
        (response) =>
            response.url().endsWith("/member/training") &&
            response.request().method() === "POST",
    );
    await page.getByRole("button", { name: "เพิ่มประวัติการอบรม" }).click();
    const replayResponse = await replayResponsePromise;
    expect(replayResponse.status()).toBe(200);
    expect(await replayResponse.json()).toMatchObject({
        code: "idempotent-replay",
    });
    expect(addTrainingKeys).toHaveLength(2);
    expect(addTrainingKeys[0]).toBeTruthy();
    expect(addTrainingKeys[1]).toBe(addTrainingKeys[0]);
    await expect(page.getByText("อบรมสติ")).toBeVisible();
    await expect(page.getByText("อบรมสติ")).toHaveCount(1);
    expect(successfulTrainingAuditCount()).toBe(
        auditCountBeforeLostResponse + 1,
    );
    expect(
        await page.evaluate(
            (operationKey) => sessionStorage.getItem(operationKey),
            trainingOperationStorageKey,
        ),
    ).toBeNull();

    const auditCountBeforeFiveHundred = successfulTrainingAuditCount();
    await page.getByLabel("ชื่อหลักสูตร").fill("อบรมอานาปานสติ");
    await page.getByLabel("หน่วยงาน/ศูนย์").fill("ศูนย์ห้าร้อย");
    await page.getByLabel("วันที่เริ่ม").fill("2026-03-10");
    await page.getByLabel("วันที่จบ").fill("2026-03-12");
    const requestCountBeforeFiveHundred = addTrainingRequests;
    await page.route("**/member/training", async (route) => {
        if (route.request().method() !== "POST") {
            await route.continue();
            return;
        }
        addTrainingRequests += 1;
        addTrainingKeys.push(
            route.request().headers()["idempotency-key"] ?? "",
        );
        if (addTrainingRequests === requestCountBeforeFiveHundred + 1) {
            const committed = await route.fetch();
            expect(committed.status()).toBe(201);
            await route.fulfill({
                status: 503,
                contentType: "application/json",
                body: JSON.stringify({
                    message: "ระบบยังยืนยันผลการบันทึกไม่ได้",
                }),
            });
            return;
        }
        await route.continue();
    });
    await page.getByRole("button", { name: "เพิ่มประวัติการอบรม" }).click();
    await expect(page.getByRole("alert")).toContainText(
        "ระบบยังยืนยันผลการบันทึกไม่ได้",
    );
    const fiveHundredKey = addTrainingKeys.at(-1);
    expect(fiveHundredKey).toBeTruthy();
    expect(
        await page.evaluate(
            (operationKey) => sessionStorage.getItem(operationKey),
            trainingOperationStorageKey,
        ),
    ).toBe(fiveHundredKey);

    await page.getByLabel("ชื่อหลักสูตร").fill("ข้อมูลใหม่ห้ามใช้คีย์เดิม");
    const conflictResponsePromise = page.waitForResponse(
        (response) =>
            response.url().endsWith("/member/training") &&
            response.request().method() === "POST",
    );
    await page.getByRole("button", { name: "เพิ่มประวัติการอบรม" }).click();
    const conflictResponse = await conflictResponsePromise;
    expect(conflictResponse.status()).toBe(409);
    expect(await conflictResponse.json()).toMatchObject({
        code: "idempotency-conflict",
    });
    expect(addTrainingKeys.at(-1)).toBe(fiveHundredKey);
    expect(
        await page.evaluate(
            (operationKey) => sessionStorage.getItem(operationKey),
            trainingOperationStorageKey,
        ),
    ).toBe(fiveHundredKey);
    await expect(
        page.getByRole("button", {
            name: "ตรวจสอบรายการแล้ว เริ่มคำขอใหม่",
        }),
    ).toBeVisible();

    await page.getByLabel("ชื่อหลักสูตร").fill("อบรมอานาปานสติ");
    const fiveHundredReplayPromise = page.waitForResponse(
        (response) =>
            response.url().endsWith("/member/training") &&
            response.request().method() === "POST",
    );
    await page.getByRole("button", { name: "เพิ่มประวัติการอบรม" }).click();
    const fiveHundredReplay = await fiveHundredReplayPromise;
    expect(fiveHundredReplay.status()).toBe(200);
    expect(await fiveHundredReplay.json()).toMatchObject({
        code: "idempotent-replay",
    });
    expect(addTrainingKeys.at(-1)).toBe(fiveHundredKey);
    await expect(page.getByText("อบรมอานาปานสติ")).toHaveCount(1);
    expect(successfulTrainingAuditCount()).toBe(
        auditCountBeforeFiveHundred + 1,
    );
    expect(
        await page.evaluate(
            (operationKey) => sessionStorage.getItem(operationKey),
            trainingOperationStorageKey,
        ),
    ).toBeNull();
    await page.unroute("**/member/training");
    const editTraining = page.getByRole("button", {
        name: "แก้ไข อบรมสติ",
    });
    await editTraining.focus();
    await page.keyboard.press("Enter");
    const editForm = page
        .getByRole("heading", { name: "แก้ไข อบรมสติ" })
        .locator("..");
    await editForm.getByLabel("ชื่อหลักสูตร").fill("อบรมสติแก้ไข");
    await editForm.getByLabel("หน่วยงาน/ศูนย์").fill("ศูนย์ทดสอบแก้ไข");
    await editForm.getByRole("button", { name: "บันทึกการแก้ไข" }).click();
    await expect(page.getByText("อบรมสติแก้ไข")).toBeVisible();
    await expect(page.getByText("ศูนย์ทดสอบแก้ไข")).toBeVisible();

    await page.goto("/member/applications");
    await expect(page.getByText("ยังไม่มีใบสมัคร")).toBeVisible();
    await expect(page.getByRole("link", { name: "ดูหลักสูตร" })).toBeVisible();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});

test("FLOW-MEMBER-01 owned non-empty application timeline is explicit and has no unsafe resume action", async ({
    page,
}) => {
    await page.goto("/signin");
    await page.getByLabel("ประเภทเอกสารประจำตัว").selectOption("personal_id");
    await page.getByLabel("เลขเอกสารประจำตัว").fill("1234567890123");
    await page.getByLabel("รหัสผ่าน").fill("TapodaLocalSeed!2026");
    await page.getByRole("button", { name: "เข้าสู่ระบบ" }).click();
    await expect(page).toHaveURL(/\/account$/);

    await page.goto("/member/applications");
    await expect(page.getByText("ฉบับร่าง", { exact: true })).toBeVisible();
    await expect(
        page.getByText("ยังไม่มีข้อมูลขั้นตอนถัดไปจากระบบใบสมัคร"),
    ).toBeVisible();
    await expect(
        page.getByText("ยังไม่มีกำหนดส่งจากระบบใบสมัคร"),
    ).toBeVisible();
    await expect(
        page.getByRole("list", { name: "ลำดับเหตุการณ์ใบสมัคร" }),
    ).toContainText("ฉบับร่าง");
    await expect(
        page.getByText("ยังไม่มีเส้นทางทำรายการต่อที่ได้รับอนุญาต"),
    ).toBeVisible();
    await expect(page.getByRole("link", { name: "ทำรายการต่อ" })).toHaveCount(
        0,
    );
});

test("FLOW-MEMBER-01 address references expose failure, retry, and ignore aborted stale responses", async ({
    page,
}, testInfo) => {
    await registerMember(page, `${Date.now()}${testInfo.workerIndex}4`);
    await page.goto("/member/profile");
    await page.getByLabel("ที่อยู่").fill("88 ถนนคงค่า");

    const pageErrors: string[] = [];
    page.on("pageerror", (error) => pageErrors.push(error.message));

    let amphoeRequests = 0;
    await page.route("**/select/amphoes?*", async (route) => {
        amphoeRequests += 1;
        if (amphoeRequests === 1) {
            await route.fulfill({
                status: 200,
                contentType: "application/json",
                body: JSON.stringify({
                    data: [null],
                    meta: {
                        status: "ok",
                        parent_type: "province",
                        parent_id: "member-bkk",
                    },
                    errors: [],
                }),
            });
            return;
        }
        if (amphoeRequests === 2) {
            const duplicate = {
                id: "member-phra",
                code: "MB1001",
                label: "พระนคร (ทดสอบ)",
            };
            await route.fulfill({
                status: 200,
                contentType: "application/json",
                body: JSON.stringify({
                    data: [duplicate, duplicate],
                    meta: {
                        status: "ok",
                        parent_type: "province",
                        parent_id: "member-bkk",
                    },
                    errors: [],
                }),
            });
            return;
        }
        if (amphoeRequests === 3) {
            await route.fulfill({
                status: 200,
                contentType: "application/json",
                body: JSON.stringify({
                    data: [
                        {
                            id: "../invalid",
                            code: "MB1001",
                            label: "พระนคร (ทดสอบ)",
                        },
                    ],
                    meta: {
                        status: "ok",
                        parent_type: "province",
                        parent_id: "member-bkk",
                    },
                    errors: [],
                }),
            });
            return;
        }
        await route.continue();
    });

    await page.getByLabel("จังหวัด").selectOption("member-bkk");
    const amphoeAlert = page.getByRole("alert").filter({
        hasText: "โหลดรายการอำเภอ/เขตไม่สำเร็จ",
    });
    await expect(amphoeAlert).toBeVisible();
    await expect(amphoeAlert).toBeFocused();
    await expect(page.getByLabel("จังหวัด")).toHaveValue("member-bkk");
    await expect(page.getByLabel("ที่อยู่")).toHaveValue("88 ถนนคงค่า");
    await expect(page.getByLabel("อำเภอ/เขต")).toBeDisabled();

    const retryAmphoes = page.getByRole("button", {
        name: "ลองโหลดอำเภอ/เขตอีกครั้ง",
    });
    await retryAmphoes.click();
    await expect(amphoeAlert).toBeVisible();
    await expect(page.getByLabel("อำเภอ/เขต")).toBeDisabled();
    await retryAmphoes.click();
    await expect(amphoeAlert).toBeVisible();
    await expect(page.getByLabel("อำเภอ/เขต")).toBeDisabled();
    await retryAmphoes.click();
    await expect(
        page.getByLabel("อำเภอ/เขต").locator('option[value="member-phra"]'),
    ).toHaveCount(1);
    await expect(page.getByLabel("อำเภอ/เขต")).toBeEnabled();

    let releaseDelayedTambons = () => {};
    const delayedTambons = new Promise<void>((resolve) => {
        releaseDelayedTambons = resolve;
    });
    let markDelayedRouteComplete = () => {};
    const delayedRouteComplete = new Promise<void>((resolve) => {
        markDelayedRouteComplete = resolve;
    });
    let tambonRequests = 0;
    await page.route("**/select/tambons?*", async (route) => {
        tambonRequests += 1;
        if (tambonRequests === 1) {
            await delayedTambons;
            await route.continue();
            markDelayedRouteComplete();
            return;
        }
        if (tambonRequests === 2) {
            await route.fulfill({
                status: 503,
                contentType: "application/json",
                body: JSON.stringify({ message: "unavailable" }),
            });
            return;
        }
        await route.continue();
    });

    await page.getByLabel("อำเภอ/เขต").selectOption("member-phra");
    await expect(
        page.getByRole("status").filter({
            hasText: "กำลังโหลดรายการตำบล/แขวง",
        }),
    ).toBeVisible();

    await page.getByLabel("จังหวัด").selectOption("");
    await expect(page.getByLabel("อำเภอ/เขต")).toHaveValue("");
    await expect(page.getByLabel("ตำบล/แขวง")).toHaveValue("");
    await page.getByLabel("จังหวัด").selectOption("member-bkk");
    await expect(
        page.getByLabel("อำเภอ/เขต").locator('option[value="member-phra"]'),
    ).toHaveCount(1);
    await page.getByLabel("อำเภอ/เขต").selectOption("member-phra");

    const tambonAlert = page.getByRole("alert").filter({
        hasText: "โหลดรายการตำบล/แขวงไม่สำเร็จ",
    });
    await expect(tambonAlert).toBeVisible();
    await expect(tambonAlert).toBeFocused();
    await page
        .getByRole("button", { name: "ลองโหลดตำบล/แขวงอีกครั้ง" })
        .click();
    await expect(
        page.getByLabel("ตำบล/แขวง").locator('option[value="member-wat"]'),
    ).toHaveCount(1);

    releaseDelayedTambons();
    await delayedRouteComplete;
    await page.evaluate(
        () =>
            new Promise<void>((resolve) =>
                window.requestAnimationFrame(() => resolve()),
            ),
    );
    await expect(page.getByLabel("จังหวัด")).toHaveValue("member-bkk");
    await expect(page.getByLabel("อำเภอ/เขต")).toHaveValue("member-phra");
    await expect(
        page.getByLabel("ตำบล/แขวง").locator('option[value="member-wat"]'),
    ).toHaveCount(1);
    expect(pageErrors).toEqual([]);
});

test("FLOW-MEMBER-01 saved address invalidates a delayed child when its parent resets", async ({
    page,
}, testInfo) => {
    await registerMember(page, `${Date.now()}${testInfo.workerIndex}5`);
    await page.goto("/member/profile");
    await page.getByLabel("ที่อยู่").fill("77 ถนนข้อมูลเดิม");
    await page.getByLabel("จังหวัด").selectOption("member-bkk");
    await expect(
        page.getByLabel("อำเภอ/เขต").locator('option[value="member-phra"]'),
    ).toHaveCount(1);
    await page.getByLabel("อำเภอ/เขต").selectOption("member-phra");
    await expect(
        page.getByLabel("ตำบล/แขวง").locator('option[value="member-wat"]'),
    ).toHaveCount(1);
    await page.getByLabel("ตำบล/แขวง").selectOption("member-wat");
    const savedAddressRefresh = page.waitForResponse(
        (response) =>
            response.url().includes("/select/tambons?") &&
            response.request().method() === "GET" &&
            response.ok(),
    );
    await page.getByRole("button", { name: "บันทึกที่อยู่" }).click();
    await expect(page.locator(".form-status")).toContainText(
        "บันทึกข้อมูลแล้ว",
    );
    await savedAddressRefresh;
    await page.waitForLoadState("networkidle");

    const pageErrors: string[] = [];
    page.on("pageerror", (error) => pageErrors.push(error.message));

    let amphoeRequests = 0;
    await page.route("**/select/amphoes?*", async (route) => {
        amphoeRequests += 1;
        if (amphoeRequests === 1) {
            await route.fulfill({
                status: 503,
                contentType: "application/json",
                body: JSON.stringify({ message: "unavailable" }),
            });
            return;
        }
        if (amphoeRequests === 2) {
            await route.fulfill({
                status: 200,
                contentType: "application/json",
                body: JSON.stringify({
                    data: [],
                    meta: {
                        status: "empty",
                        parent_type: "province",
                        parent_id: "member-bkk",
                    },
                    errors: [],
                }),
            });
            return;
        }
        await route.continue();
    });

    let releaseOldTambons = () => {};
    const oldTambons = new Promise<void>((resolve) => {
        releaseOldTambons = resolve;
    });
    let completeOldTambons = () => {};
    const oldTambonsComplete = new Promise<void>((resolve) => {
        completeOldTambons = resolve;
    });
    let tambonRequests = 0;
    await page.route("**/select/tambons?*", async (route) => {
        tambonRequests += 1;
        try {
            await oldTambons;
            await route.continue();
        } finally {
            completeOldTambons();
        }
    });

    await page.reload();
    await expect(
        page.getByRole("alert").filter({
            hasText: "โหลดรายการอำเภอ/เขตไม่สำเร็จ",
        }),
    ).toBeFocused();
    expect(
        tambonRequests,
        "a rejected saved amphoe must prevent its child request from starting",
    ).toBe(0);
    const oldChildWasStarted = tambonRequests > 0;

    await page
        .getByRole("button", { name: "ลองโหลดอำเภอ/เขตอีกครั้ง" })
        .click();
    await expect(page.getByLabel("อำเภอ/เขต")).toHaveValue("");
    await expect(page.getByLabel("ตำบล/แขวง")).toHaveValue("");

    releaseOldTambons();
    if (oldChildWasStarted) await oldTambonsComplete;
    await page.evaluate(
        () =>
            new Promise<void>((resolve) =>
                window.requestAnimationFrame(() =>
                    window.requestAnimationFrame(() => resolve()),
                ),
            ),
    );
    await expect(
        page.getByLabel("ตำบล/แขวง").locator('option[value="member-wat"]'),
    ).toHaveCount(0);

    await page.unroute("**/select/amphoes?*");
    await page.unroute("**/select/tambons?*");
    await page.getByLabel("จังหวัด").selectOption("");
    await page.getByLabel("จังหวัด").selectOption("member-bkk");
    await expect(
        page.getByLabel("อำเภอ/เขต").locator('option[value="member-phra"]'),
    ).toHaveCount(1);
    await page.getByLabel("อำเภอ/เขต").selectOption("member-phra");
    await expect(
        page.getByLabel("ตำบล/แขวง").locator('option[value="member-wat"]'),
    ).toHaveCount(1);
    expect(pageErrors).toEqual([]);
});

test("FLOW-MEMBER-01 stale and interrupted updates are explicit, retryable, and focus the error", async ({
    page,
}, testInfo) => {
    await registerMember(page, `${Date.now()}${testInfo.workerIndex}2`);
    await page.goto("/member/profile");
    await page.route("**/member/profile", async (route) => {
        if (route.request().method() === "PUT") {
            await route.fulfill({
                status: 409,
                contentType: "application/json",
                body: JSON.stringify({
                    message: "ข้อมูลถูกแก้ไขจากอุปกรณ์อื่น กรุณาโหลดใหม่",
                    code: "stale",
                }),
            });
            return;
        }
        await route.continue();
    });
    await page.getByRole("button", { name: "บันทึกข้อมูลส่วนตัว" }).click();
    await expect(page.getByRole("alert")).toContainText("อุปกรณ์อื่น");
    await expect(page.getByRole("alert")).toBeFocused();
    await expect(page.getByRole("button", { name: "โหลดใหม่" })).toBeVisible();
    await page.unroute("**/member/profile");

    await page.route("**/member/profile", async (route) => {
        if (route.request().method() === "PUT") {
            await route.abort("connectionfailed");
            return;
        }
        await route.continue();
    });
    await page.getByRole("button", { name: "บันทึกข้อมูลส่วนตัว" }).click();
    await expect(page.getByRole("alert")).toContainText("เชื่อมต่อระบบไม่ได้");
    await expect(page.getByRole("alert")).toBeFocused();
});

test("FLOW-MEMBER-01 password stays Identity-owned, redacted, and revokes the old credential", async ({
    page,
}, testInfo) => {
    const identity = await registerMember(
        page,
        `${Date.now()}${testInfo.workerIndex}3`,
    );
    await page.goto("/member/password");
    const browserMessages: string[] = [];
    const pageErrors: string[] = [];
    page.on("console", (message) => browserMessages.push(message.text()));
    page.on("pageerror", (error) => pageErrors.push(error.message));
    const current = page.getByLabel("รหัสผ่านปัจจุบัน");
    const replacement = page.getByLabel("รหัสผ่านใหม่", { exact: true });
    const confirmation = page.getByLabel("ยืนยันรหัสผ่านใหม่");

    const rejectedPayloads = [
        {
            current: "wrong-current-secret-456",
            replacement: "negative-password-456",
            confirmation: "negative-password-456",
            field: "current_password",
            message: "รหัสผ่านปัจจุบันไม่ถูกต้อง",
        },
        {
            current: originalPassword,
            replacement: "123456789012",
            confirmation: "123456789012",
            field: "password",
            message: "รหัสผ่านใหม่ต้องมีตัวอักษรอย่างน้อย 1 ตัว",
        },
        {
            current: originalPassword,
            replacement: "negative-password-789",
            confirmation: "different-password-012",
            field: "password_confirmation",
            message: "คำยืนยันรหัสผ่านใหม่ไม่ตรงกับรหัสผ่านใหม่",
        },
    ] as const;

    for (const rejected of rejectedPayloads) {
        const rejectedResponsePromise = page.waitForResponse(
            (response) =>
                response.url().endsWith("/account/password") &&
                response.request().method() === "POST",
        );
        await current.fill(rejected.current);
        await replacement.fill(rejected.replacement);
        await confirmation.fill(rejected.confirmation);
        await page.getByRole("button", { name: "เปลี่ยนรหัสผ่าน" }).click();
        const rejectedResponse = await rejectedResponsePromise;
        expect(rejectedResponse.status()).toBe(422);
        const responseText = await rejectedResponse.text();
        for (const secret of [
            rejected.current,
            rejected.replacement,
            rejected.confirmation,
        ]) {
            expect(responseText).not.toContain(secret);
        }
        await expect(page.locator(`#${rejected.field}-error`)).toHaveText(
            rejected.message,
        );
        await expect(page.locator(`[name="${rejected.field}"]`)).toBeFocused();
        await expect(page).toHaveURL(/\/member\/password$/);
    }

    const accountResponse = await page.goto("/account");
    expect(accountResponse?.status()).toBe(200);
    await page.goto("/member/password");
    const responsePromise = page.waitForResponse(
        (response) =>
            response.url().endsWith("/account/password") &&
            response.request().method() === "POST",
    );
    await page.getByLabel("รหัสผ่านปัจจุบัน").fill(originalPassword);
    await page
        .getByLabel("รหัสผ่านใหม่", { exact: true })
        .fill(replacementPassword);
    await page.getByLabel("ยืนยันรหัสผ่านใหม่").fill(replacementPassword);
    await page.getByRole("button", { name: "เปลี่ยนรหัสผ่าน" }).click();
    const response = await responsePromise;
    const responseText = await response.text();
    expect(responseText).not.toContain(originalPassword);
    expect(responseText).not.toContain(replacementPassword);
    expect(page.url()).not.toContain(originalPassword);
    expect(page.url()).not.toContain(replacementPassword);
    await expect(page.locator(".form-status")).toContainText(
        "เปลี่ยนรหัสผ่านแล้ว",
    );
    for (const secret of [
        originalPassword,
        replacementPassword,
        ...rejectedPayloads.flatMap((payload) => [
            payload.current,
            payload.replacement,
            payload.confirmation,
        ]),
    ]) {
        expect(browserMessages.join("\n")).not.toContain(secret);
        expect(pageErrors.join("\n")).not.toContain(secret);
    }

    await page.goto("/account");
    await page.getByRole("button", { name: "ออกจากระบบ" }).click();
    await page.getByLabel("ประเภทเอกสารประจำตัว").selectOption("passport");
    await page.getByLabel("เลขเอกสารประจำตัว").fill(identity);
    await page.getByLabel("รหัสผ่าน").fill(replacementPassword);
    await page.getByRole("button", { name: "เข้าสู่ระบบ" }).click();
    await expect(page).toHaveURL(/\/account$/);
});
