import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

const readyResponse = {
    status: "ready",
    build: {
        version: "browser-test",
        commit: "0123456789ab",
    },
    checks: {
        database: "ok",
        redis: "ok",
        queue: "ok",
        scheduler: "ok",
        migrations: {
            status: "ok",
            pending: 0,
        },
    },
};

const degradedResponse = {
    status: "degraded",
    build: {
        version: "browser-test",
        commit: "0123456789ab",
    },
    checks: {
        database: "failed",
        redis: "ok",
        queue: "stale",
        scheduler: "stale",
        migrations: {
            status: "pending",
            pending: 2,
        },
    },
};

async function mockReadiness(
    page: Page,
    response: typeof readyResponse | typeof degradedResponse = readyResponse,
    status = 200,
): Promise<void> {
    await page.route("**/health/ready", async (route) => {
        await route.fulfill({
            status,
            contentType: "application/json",
            body: JSON.stringify(response),
        });
    });
}

test("live local stack exposes real dependency readiness", async ({
    request,
    baseURL,
}) => {
    const origin = new URL(baseURL ?? "");
    expect(["127.0.0.1", "localhost", "host.docker.internal"]).toContain(
        origin.hostname,
    );

    await expect
        .poll(async () => (await request.get("/health/ready")).status(), {
            timeout: 30_000,
        })
        .toBe(200);

    const response = await request.get("/health/ready");
    expect(response.headers()["set-cookie"]).toBeUndefined();
    expect(await response.json()).toMatchObject({
        status: "ready",
        checks: {
            database: "ok",
            redis: "ok",
            queue: "ok",
            scheduler: "ok",
            migrations: {
                status: "ok",
                pending: 0,
            },
        },
    });
});

test("Thai system state is accessible and keyboard operable", async ({
    page,
}) => {
    await mockReadiness(page);
    await page.goto("/_local/system-state");

    await expect(page.locator("html")).toHaveAttribute("lang", "th");
    await expect(
        page.getByRole("heading", { level: 1, name: "สถานะระบบ" }),
    ).toBeVisible();
    await expect(
        page.getByRole("heading", { level: 2, name: "ระบบพร้อมใช้งาน" }),
    ).toBeVisible();

    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);

    await page.keyboard.press("Tab");
    await expect(
        page.getByRole("link", { name: "ข้ามไปยังเนื้อหาหลัก" }),
    ).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(
        page.getByRole("button", { name: "ตรวจสอบอีกครั้ง" }),
    ).toBeFocused();
});

test("degraded state exposes failed, stale, and pending checks accessibly", async ({
    page,
}) => {
    await mockReadiness(page, degradedResponse, 503);
    await page.setViewportSize({ width: 320, height: 800 });
    await page.goto("/_local/system-state");

    await expect(
        page.getByRole("heading", { level: 2, name: "ระบบยังไม่พร้อม" }),
    ).toBeVisible();
    await expect(page.getByText("เชื่อมต่อไม่ได้")).toBeVisible();
    await expect(page.getByText("ไม่ได้รับสัญญาณล่าสุด")).toHaveCount(2);
    await expect(page.getByText("รออัปเดต")).toBeVisible();
    await expect(page.getByRole("status")).toContainText(
        "ระบบยังไม่พร้อมใช้งาน",
    );
    const failedColor = await page
        .locator('[data-state="failed"]')
        .evaluate((element) => getComputedStyle(element).color);
    const staleColor = await page
        .locator('[data-state="stale"]')
        .first()
        .evaluate((element) => getComputedStyle(element).color);
    const pendingColor = await page
        .locator('[data-state="pending"]')
        .evaluate((element) => getComputedStyle(element).color);
    expect(failedColor).not.toBe(staleColor);
    expect(pendingColor).toBe(staleColor);
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(320);

    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);
});

test("unavailable state announces failure and refresh recovery", async ({
    page,
}) => {
    let attempt = 0;
    await page.route("**/health/ready", async (route) => {
        attempt += 1;

        if (attempt === 1) {
            await route.abort("failed");
            return;
        }

        await new Promise((resolve) => setTimeout(resolve, 200));
        await route.fulfill({
            status: 200,
            contentType: "application/json",
            body: JSON.stringify(readyResponse),
        });
    });
    await page.goto("/_local/system-state");

    await expect(page.getByRole("alert")).toContainText(
        "ไม่สามารถอ่านสถานะได้ในขณะนี้",
    );
    await expect(page.getByRole("status")).toContainText(
        "ระบบยังไม่พร้อมใช้งาน",
    );
    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);

    await page.getByRole("button", { name: "ตรวจสอบอีกครั้ง" }).click();
    await expect(
        page.getByRole("button", { name: "กำลังตรวจสอบ…" }),
    ).toBeDisabled();
    await expect(page.getByRole("status")).toContainText(
        "กำลังตรวจสอบสถานะระบบ",
    );
    await expect(
        page.getByRole("heading", { level: 2, name: "ระบบพร้อมใช้งาน" }),
    ).toBeVisible();
    await expect(page.getByRole("alert")).toHaveCount(0);
});

test("system state reflows at 320px and 200 percent zoom", async ({ page }) => {
    await mockReadiness(page);
    await page.setViewportSize({ width: 320, height: 800 });
    await page.goto("/_local/system-state");
    await expect(
        page.getByRole("heading", { level: 2, name: "ระบบพร้อมใช้งาน" }),
    ).toBeVisible();
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(320);

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.evaluate(() => {
        document.documentElement.style.zoom = "2";
    });
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth <=
                document.documentElement.clientWidth,
        ),
    ).toBe(true);
});

test("system state visual baseline", async ({ page }) => {
    await mockReadiness(page);
    await page.goto("/_local/system-state");
    await expect(
        page.getByRole("heading", { level: 2, name: "ระบบพร้อมใช้งาน" }),
    ).toBeVisible();
    await page.locator(".build").evaluate((element) => {
        element.textContent = "รุ่น local · unknown";
    });

    await expect(page).toHaveScreenshot("system-state.png", {
        fullPage: true,
        animations: "disabled",
    });
});
