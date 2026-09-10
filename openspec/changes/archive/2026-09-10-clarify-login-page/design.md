# Clarify login page

## Direction

Keep the current distilled single-column login composition and improve only the language and semantic relationships. The page should read as one short sequence:

1. **Destination:** “Sign in to your CRM account.”
2. **Instruction:** “Use your username and password to continue.”
3. **Credentials:** persistent Username and Password labels.
4. **Work context:** “Choose a campaign” with a short explanation that the selection becomes the starting campaign after sign-in.
5. **Action:** “Sign in.”

## Interaction details

- Keep the compact configured brand lockup above the auth sheet.
- Keep the campaign control native and preserve the existing option values and old-input behavior.
- Add the campaign explanation as visible helper text and connect it to the select with `aria-describedby`.
- Start the theme toggle with the specific action “Switch to light mode”; the existing script continues to update it for the active theme.
- Use privacy-safe, recovery-oriented invalid-credential feedback: “We couldn't sign you in with those details. Check your username and password, then try again.”
- Keep feedback inside the existing semantic `role="alert"` region and avoid adding a second alert or modal.
- Preserve existing focus, contrast, reduced-motion, touch-target, and no-overflow behavior.
