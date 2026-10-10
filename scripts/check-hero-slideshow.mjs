import assert from 'node:assert/strict';
import { expect } from '@playwright/test';

export async function checkHeroSlideshow(browser, base) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'no-preference' });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        const time = new Date('2026-10-10T00:00:00Z');
        await page.clock.install({ time });
        await page.clock.pauseAt(time);
        await page.goto(base);
        const slides = page.locator('[data-hero-slide]');
        const choices = page.locator('[data-hero-select]');
        const toggle = page.locator('[data-hero-toggle]');
        await expect(page.locator('[data-hero-controls]')).toBeVisible();
        await slides.locator('img').evaluateAll(images => Promise.all(images.map(image => image.decode())));
        assert.equal(await slides.count(), 4);
        const buttons = await page.locator('[data-hero-controls] button').evaluateAll(nodes => nodes.map(node => ({ width: node.offsetWidth, height: node.offsetHeight })));
        assert.ok(buttons.every(button => button.width >= 48 && button.height >= 48), '48px controls');

        // Restart from a known point, then verify the actual 3-second boundary.
        await toggle.click();
        await expect(toggle).toHaveAccessibleName('Putar pergantian foto');
        await toggle.click();
        await page.mouse.move(0, 0);
        await page.clock.runFor(2999);
        await expect(choices.nth(0)).toHaveAttribute('aria-pressed', 'true');
        await page.clock.runFor(1);
        await expect(choices.nth(1)).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('[data-hero-caption]')).toHaveText('Taman bermain yang teduh');
        for (const i of [2, 3, 0]) {
            await page.clock.runFor(3000);
            await expect(choices.nth(i)).toHaveAttribute('aria-pressed', 'true');
        }
        assert.equal(await page.locator('[data-hero-slide][aria-hidden="false"]').count(), 1);

        // Hover is a temporary pause; leaving starts a fresh interval.
        await page.locator('.hero-arch').hover();
        await page.clock.runFor(6000);
        await expect(choices.nth(0)).toHaveAttribute('aria-pressed', 'true');
        await page.mouse.move(0, 0);
        await page.clock.runFor(3000);
        await expect(choices.nth(1)).toHaveAttribute('aria-pressed', 'true');

        await toggle.click();
        await expect(toggle).toHaveAccessibleName('Putar pergantian foto');
        await page.mouse.move(0, 0);
        await page.clock.runFor(6000);
        await expect(choices.nth(1)).toHaveAttribute('aria-pressed', 'true');
        await choices.nth(3).click();
        await expect(slides.nth(3)).toHaveAttribute('aria-hidden', 'false');
        await expect(page.locator('[data-hero-caption]')).toHaveText('Lapangan serbaguna');

        // Moving keyboard focus to a photo keeps that slide still.
        await toggle.click();
        await page.mouse.move(0, 0);
        await choices.nth(2).focus();
        await expect(toggle).toHaveAccessibleName('Putar pergantian foto');
        await page.keyboard.press('Enter');
        await expect(choices.nth(2)).toHaveAttribute('aria-pressed', 'true');
        await page.clock.runFor(6000);
        await expect(choices.nth(2)).toHaveAttribute('aria-pressed', 'true');

        // Photos stop changing while the hero is outside the viewport.
        await toggle.click();
        await page.mouse.move(0, 0);
        await page.locator('.site-footer').scrollIntoViewIfNeeded();
        await expect(page.locator('.hero-arch')).not.toBeInViewport();
        await page.clock.runFor(6000);
        await expect(choices.nth(2)).toHaveAttribute('aria-pressed', 'true');
        await page.locator('.hero-arch').scrollIntoViewIfNeeded();
        await expect(page.locator('.hero-arch')).toBeInViewport();
        await page.clock.runFor(3000);
        await expect(choices.nth(3)).toHaveAttribute('aria-pressed', 'true');

        // A live change of OS preference stops autoplay as well as the fade.
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await expect(toggle).toHaveAccessibleName('Putar pergantian foto');
        await page.clock.runFor(6000);
        await expect(choices.nth(3)).toHaveAttribute('aria-pressed', 'true');
        assert.equal(await slides.nth(3).evaluate(node => getComputedStyle(node).transitionDuration), '0s');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }

    // Both reduced-motion and no-JavaScript visits start with a readable gapura.
    for (const options of [{ reducedMotion: 'reduce' }, { javaScriptEnabled: false }]) {
        const context = await browser.newContext({ ...options, viewport: { width: 390, height: 844 } });
        try {
            const page = await context.newPage();
            await page.goto(base);
            await expect(page.locator('[data-hero-slide]').nth(0)).toHaveAttribute('aria-hidden', 'false');
            if (options.javaScriptEnabled === false) {
                await expect(page.locator('[data-hero-controls]')).toBeHidden();
            } else {
                await expect(page.locator('[data-hero-toggle]')).toHaveAccessibleName('Putar pergantian foto');
                await page.waitForTimeout(3100);
                await expect(page.locator('[data-hero-select]').nth(0)).toHaveAttribute('aria-pressed', 'true');
            }
        } finally {
            await context.close();
        }
    }
    return { intervalMs: 3000, photos: 4, timing: 'PASS', wraparound: 'PASS', pauseAndSelect: 'PASS', keyboardAndHover: 'PASS', offscreen: 'PASS', reducedMotion: 'PASS', noJavaScript: 'PASS' };
}
