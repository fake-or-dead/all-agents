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

async function mockReadiness(page: Page): Promise<void> {
    await page.route("**/health/ready", async (route) => {
        await route.fulfill({
            status: 200,
            contentType: "application/json",
            body: JSON.stringify(readyResponse),
        });
    });
}

test("Thai system state is accessible and keyboard operable", async ({
    page,
}) => {
    await mockReadiness(page);
    await page.goto("/");

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

test("system state reflows at 320px and 200 percent zoom", async ({ page }) => {
    await mockReadiness(page);
    await page.setViewportSize({ width: 320, height: 800 });
    await page.goto("/");
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
    await page.goto("/");
    await expect(
        page.getByRole("heading", { level: 2, name: "ระบบพร้อมใช้งาน" }),
    ).toBeVisible();

    await expect(page).toHaveScreenshot("system-state.png", {
        fullPage: true,
        animations: "disabled",
    });
});
