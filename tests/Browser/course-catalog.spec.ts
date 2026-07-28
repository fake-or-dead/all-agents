import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

test("catalog is Thai-first, accessible, keyboard operable, and filterable", async ({
    page,
}) => {
    await page.goto(
        "/course?year=2026&month=8&course_type=meditation&center=tapoda",
    );

    await expect(page.locator("html")).toHaveAttribute("lang", "th");
    await expect(
        page.getByRole("heading", { level: 1, name: "ค้นหาหลักสูตร" }),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: "หลักสูตรปฏิบัติธรรม 10 วัน" }),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: "หลักสูตรเตรียมจิตอาสา" }),
    ).toHaveCount(0);
    await expect(page.locator('script[type="module"]')).toHaveCount(0);

    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);

    await page.keyboard.press("Tab");
    await expect(
        page.getByRole("link", { name: "ข้ามไปยังเนื้อหาหลัก" }),
    ).toBeFocused();
    await page.keyboard.press("Enter");
    await expect(page.locator("#main-content")).toBeFocused();
});

for (const width of [320, 375, 768, 1024, 1440]) {
    test(`catalog reflows without horizontal overflow at ${width}px`, async ({
        page,
    }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto("/course");

        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth <=
                    document.documentElement.clientWidth,
            ),
        ).toBe(true);
    });
}

test("catalog stays usable at 200 percent zoom", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto("/course");
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

test("course detail exposes truthful policy and local document outcome", async ({
    page,
}) => {
    await page.goto(
        "/course/detail/D10-2026-08-TAPODA?age=30&category=female&applicant_type=trainee",
    );

    await expect(
        page.getByRole("heading", {
            level: 1,
            name: "หลักสูตรปฏิบัติธรรม 10 วัน",
        }),
    ).toBeVisible();
    await expect(
        page.getByRole("heading", { level: 3, name: "ผ่านเกณฑ์เบื้องต้น" }),
    ).toBeVisible();
    await expect(page.getByText("เหลือ 2 จาก 30 ที่นั่ง")).toBeVisible();
    await expect(
        page.getByRole("link", { name: /เปิดแผนที่ภายนอก/ }),
    ).toHaveAttribute("rel", "external noopener");

    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);

    await page
        .getByRole("link", { name: "คู่มือเตรียมตัวเข้าร่วมหลักสูตร" })
        .click();
    await expect(
        page.getByRole("heading", {
            level: 1,
            name: "ยังไม่มีเอกสารในระบบท้องถิ่น",
        }),
    ).toBeVisible();
});

test("catalog remains crawlable with JavaScript disabled", async ({
    browser,
    baseURL,
}) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto(`${baseURL}/course`);

    await expect(
        page.getByRole("heading", { level: 1, name: "ค้นหาหลักสูตร" }),
    ).toBeVisible();
    await expect(page.getByRole("button", { name: "ค้นหา" })).toBeVisible();
    await expect(
        page
            .getByRole("link", { name: "หลักสูตรปฏิบัติธรรม 10 วัน" })
            .first(),
    ).toBeVisible();

    await context.close();
});

test("catalog visual baseline", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto("/course?year=2026");
    await expect(page).toHaveScreenshot("course-catalog.png", {
        fullPage: true,
        animations: "disabled",
    });
});
