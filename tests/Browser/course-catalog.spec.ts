import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

test("catalog is Thai-first, accessible, keyboard operable, and filterable", async ({
    page,
}) => {
    const catalogResponse = await page.goto(
        "/course?year=2026&month=8&course_type=meditation&center=tapoda",
    );

    expect(catalogResponse?.headers()["referrer-policy"]).toBe("no-referrer");
    await expect(page.locator("html")).toHaveAttribute("lang", "th");
    await expect(
        page.getByRole("heading", { level: 1, name: "ค้นหาหลักสูตร" }),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: "หลักสูตรปฏิบัติธรรม 10 วัน" }),
    ).toBeVisible();
    await expect(page.getByLabel("ปี พ.ศ.")).toHaveValue("2026");
    await expect(page.getByLabel("เดือน")).toHaveValue("8");
    await expect(page.getByLabel("เดือน").locator("option:checked")).toHaveText(
        "สิงหาคม",
    );
    await expect(page.getByText("10 สิงหาคม 2569")).toBeVisible();
    await expect(page.getByText("20 สิงหาคม 2569")).toBeVisible();
    await expect(
        page.getByRole("link", { name: "หลักสูตรเตรียมจิตอาสา" }),
    ).toHaveCount(0);
    await expect(page.locator('script[type="module"]')).toHaveCount(0);
    await page.evaluate(() => document.fonts.ready);
    expect(
        await page.evaluate(() => document.fonts.check("16px Sarabun")),
    ).toBe(true);
    await expect(page.locator("html")).toHaveCSS(
        "font-family",
        /Sarabun.*system-ui.*sans-serif/,
    );

    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);

    await page.keyboard.press("Tab");
    await expect(
        page.getByRole("link", { name: "ข้ามไปยังเนื้อหาหลัก" }),
    ).toBeFocused();
    await page.keyboard.press("Enter");
    await expect(page.locator("#main-content")).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.getByLabel("ปี พ.ศ.")).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.getByLabel("เดือน")).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.getByLabel("ประเภทหลักสูตร")).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.getByLabel("ศูนย์")).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.getByRole("button", { name: "ค้นหา" })).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.getByRole("link", { name: "ล้างตัวกรอง" })).toBeFocused();
});

for (const viewport of [
    { width: 1440, height: 900 },
    { width: 1185, height: 811 },
    { width: 1024, height: 768 },
    { width: 768, height: 1024 },
    { width: 375, height: 812 },
    { width: 320, height: 568 },
]) {
    test(`catalog reflows without horizontal overflow at ${viewport.width}x${viewport.height}`, async ({
        page,
    }) => {
        await page.setViewportSize(viewport);
        await page.goto("/course");

        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth <=
                    document.documentElement.clientWidth,
            ),
        ).toBe(true);

        if (viewport.width >= 768) {
            const cards = await page
                .locator(".course-card")
                .evaluateAll((elements) =>
                    elements.map(
                        (element) => element.getBoundingClientRect().bottom,
                    ),
                );

            expect(cards).not.toHaveLength(0);
            expect(cards.every((bottom) => bottom <= viewport.height)).toBe(
                true,
            );
        }
    });
}

for (const viewport of [
    { name: "desktop", width: 1440, height: 900 },
    { name: "mobile", width: 375, height: 812 },
]) {
    test(`catalog cards stay fully bounded at the ${viewport.name} viewport`, async ({
        page,
    }) => {
        await page.setViewportSize(viewport);
        await page.goto("/course?year=2026");

        const layout = await page.locator(".course-card").evaluateAll((cards) =>
            cards.map((card) => {
                const cardRect = card.getBoundingClientRect();
                const descendants = [...card.querySelectorAll("*")].map(
                    (element) => element.getBoundingClientRect(),
                );

                return {
                    left: cardRect.left,
                    right: cardRect.right,
                    bottom: cardRect.bottom,
                    contentIsBounded: descendants.every(
                        (rect) =>
                            rect.left >= cardRect.left - 1 &&
                            rect.right <= cardRect.right + 1 &&
                            rect.top >= cardRect.top - 1 &&
                            rect.bottom <= cardRect.bottom + 1,
                    ),
                };
            }),
        );

        expect(layout).not.toHaveLength(0);
        expect(
            layout.every(
                (card) =>
                    card.left >= 0 &&
                    card.right <= viewport.width &&
                    card.contentIsBounded,
            ),
        ).toBe(true);
        const targetHeights = await page
            .locator(
                ".filter-panel select, .filter-panel button, .filter-panel .secondary-action",
            )
            .evaluateAll((elements) =>
                elements.map(
                    (element) => element.getBoundingClientRect().height,
                ),
            );
        expect(targetHeights.every((height) => height >= 44)).toBe(true);

        if (viewport.name === "desktop") {
            expect(layout.every((card) => card.bottom <= viewport.height)).toBe(
                true,
            );

            const visualScale = await page.evaluate(() => {
                const heading = document.querySelector("h1");
                const filter = document.querySelector(".filter-panel");

                if (!(heading instanceof HTMLElement) || !filter) {
                    return null;
                }

                return {
                    headingFontSize: Number.parseFloat(
                        getComputedStyle(heading).fontSize,
                    ),
                    filterHeight: filter.getBoundingClientRect().height,
                };
            });

            expect(visualScale).not.toBeNull();
            expect(visualScale?.headingFontSize).toBeLessThanOrEqual(36);
            expect(visualScale?.filterHeight).toBeLessThanOrEqual(180);
        }
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
    await page.goto("/course/detail/D10-2026-08-TAPODA");

    await expect(
        page.getByRole("heading", {
            level: 1,
            name: "หลักสูตรปฏิบัติธรรม 10 วัน",
        }),
    ).toBeVisible();
    await expect(page.getByText("เหลือ 2 จาก 30 ที่นั่ง")).toBeVisible();
    await expect(page.getByText("10 สิงหาคม 2569")).toBeVisible();
    await expect(
        page.getByText("1 กรกฎาคม 2569 เวลา 00:00 น. (Asia/Bangkok)"),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: /เปิดแผนที่ภายนอก/ }),
    ).toHaveAttribute("rel", "external noopener");

    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);

    await page.getByLabel("อายุ").fill("30");
    await page.getByLabel("ประเภทบุคคล").selectOption("female");
    await page.getByLabel("รูปแบบการสมัคร").selectOption("trainee");
    const eligibilityResponsePromise = page.waitForResponse(
        (response) =>
            response.request().method() === "POST" &&
            response.url().includes("/eligibility"),
    );
    await page.getByRole("button", { name: "ประเมินสิทธิ์" }).click();
    const eligibilityResponse = await eligibilityResponsePromise;
    expect(eligibilityResponse.headers()["referrer-policy"]).toBe(
        "no-referrer",
    );
    await expect(
        page.getByRole("heading", { level: 3, name: "ผ่านเกณฑ์เบื้องต้น" }),
    ).toBeVisible();
    await expect(page).not.toHaveURL(/age=|category=|applicant_type=/);

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
        page.getByRole("link", { name: "หลักสูตรปฏิบัติธรรม 10 วัน" }).first(),
    ).toBeVisible();

    await context.close();
});

test("catalog visual baseline", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto("/course?year=2026");
    await expect(page).toHaveScreenshot("course-catalog.png", {
        fullPage: true,
        animations: "disabled",
        // CI remains pixel-exact. Local Linux containers may rasterize glyph edges
        // differently; the reviewed CI artifact differed by 6,200 text-edge pixels.
        maxDiffPixels: process.env.CI ? 0 : 6500,
    });
});
