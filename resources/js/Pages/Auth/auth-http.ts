export async function submitJson(
    url: string,
    csrfToken: string,
    body: Record<string, unknown>,
): Promise<{ ok: boolean; status: number; body: Record<string, unknown> }> {
    return requestJson(url, csrfToken, body, "POST");
}

export async function requestJson(
    url: string,
    csrfToken: string,
    body: Record<string, unknown>,
    method: "POST" | "PUT" | "DELETE",
    additionalHeaders: Record<string, string> = {},
): Promise<{ ok: boolean; status: number; body: Record<string, unknown> }> {
    try {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
                ...additionalHeaders,
            },
            body: JSON.stringify(body),
        });
        const responseText = await response.text();
        let responseBody: Record<string, unknown> = {};

        try {
            const parsed: unknown = JSON.parse(responseText);
            if (typeof parsed === "object" && parsed !== null) {
                responseBody = parsed as Record<string, unknown>;
            }
        } catch {
            responseBody = {};
        }

        if (!response.ok && typeof responseBody.message !== "string") {
            responseBody.message = "ระบบตอบกลับไม่สมบูรณ์ กรุณาลองใหม่อีกครั้ง";
        }

        return { ok: response.ok, status: response.status, body: responseBody };
    } catch {
        return {
            ok: false,
            status: 0,
            body: {
                message: "เชื่อมต่อระบบไม่ได้ กรุณาตรวจสอบเครือข่ายแล้วลองใหม่",
            },
        };
    }
}

export function formValues(form: HTMLFormElement): Record<string, string> {
    const values: Record<string, string> = {};

    new FormData(form).forEach((value, key) => {
        if (typeof value === "string") {
            values[key] = value;
        }
    });

    return values;
}
