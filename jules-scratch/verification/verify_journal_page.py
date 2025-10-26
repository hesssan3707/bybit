
from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # Go to the login page
    page.goto("http://localhost:5173/login", timeout=60000)

    # Fill in the login form
    page.fill('input[name="email"]', "admin@example.com")
    page.fill('input[name="password"]', "password")

    # Click the login button
    page.click('button[type="submit"]')
    page.wait_for_url("http://localhost:5173/futures/orders", timeout=60000)

    # Go to the journal page
    page.goto("http://localhost:5173/futures/journal", timeout=60000)

    # Take a screenshot
    page.screenshot(path="jules-scratch/verification/journal_page.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
