
from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # Go to the login page
    page.goto("http://localhost:5173/login", timeout=60000)

    # Take a screenshot for debugging
    page.screenshot(path="jules-scratch/verification/login_page_debug.png")

    # Print the page content for debugging
    print(page.content())

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
