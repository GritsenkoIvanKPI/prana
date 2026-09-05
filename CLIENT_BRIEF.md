# Client Brief — PRANA

## Business
Landscaping & lawn care company on the Costa del Sol (Marbella / Puerto Banús / Benahavís / Estepona / Sotogrande). Specialism: lawns (natural & artificial, drought-resistant Andalusian varieties, irrigation, seasonal maintenance).

## Company name
PRANA

## Languages
EN / RU / ES switcher required in header. Only English content is being built right now — RU/ES are visible in the switcher but not yet functional (placeholder / "coming soon").

## Logo
Dark green and gold, minimalist wordmark/mark (no ready-made asset provided — design in CSS/SVG).

## Contact details
Not provided — using realistic Costa del Sol placeholders (phone, WhatsApp, email, office/showroom address, CIF). Clearly swappable later.

## Design reference
Match the "Lunarch" Framer architecture template exactly for layout, spacing, component patterns and typographic system (reference screenshots: lunarch.framer.website_.png, 2.png, 3.png, mobile.png):
- Near-black base background, white headings, muted grey secondary text, gold accent
- Full-bleed photography with gradient overlays, soft radial vignette/blur mask on hero
- Numbered "01/02/03" expertise/services list with full-bleed image under each
- 4-column stats bar (years / properties / awards / satisfaction)
- Portfolio "Featured works" grid: category tag pills + title + location, big image
- Accordion process/FAQ pattern (Step 01, chevron expand)
- Awards/timeline row pattern (reused for team or packages)
- Centered quote section on dotted/grid texture background
- Nav: logo left, links, phone/WhatsApp + CTA button right, hamburger
- Buttons: solid white/gold pill, sharp-ish corners, black text

Adapted brand palette: deep dark green (near-black-green) replacing neutral black, warm muted gold accent replacing reference gold — chosen by Claude, not user-specified exact hex.

## Page structure (single page, in order)
1. Header — logo, section nav, phone/WhatsApp, language switcher (EN/RU/ES)
2. Hero — heading, short subheading, "Submit an enquiry" CTA
3. About us / Philosophy — approach, years in business, number of properties, history
4. Portfolio — grid of properties, before/after photos
5. Services — 4 areas: landscape design, planting/landscaping, lawns & irrigation, pools/water features
6. Lawns (specialism, prominent) — lawn types (natural/artificial, drought-resistant Andalusian varieties), automatic irrigation & water conservation, seasonal maintenance (mowing, aeration, fertilisation)
7. Service packages — 3 tiers
8. Team — photos + roles (architect, designer, etc.)
9. FAQ — 5–7 questions (timelines, difficult terrain, etc.)
10. Contact — phone, WhatsApp, email, office/showroom address, languages spoken, service areas, map
11. Footer — CIF, privacy policy link, social icons

## Images
All photography generated via Gemini (gemini-3-pro-image-preview) per GEMINI_IMAGE_GENERATION.md — Mediterranean/Andalusian gardens, villas, pools, lawns, irrigation, team portraits. No stock/placeholder images where a generated photo is feasible.

## Portfolio case studies (real, client-provided)
Six real case studies (originally supplied in Russian, translated/shortened for the site), each with a before/after image pair:
1. Ático Cancelada — 40m² sea-view terrace, lightweight planters + living wall due to weight limits
2. Villa La Quinta — 850m² bare plot to full garden + lawn in 5 weeks, -30% water use
3. Villa Sierra Blanca (Marbella) — 600m² family lawn restored from disease/wear in 10 days
4. Villa Calahonda — 280m² natural lawn replaced with artificial turf, -40% upkeep cost
5. Villa Mijas Costa — 700m² garden neglected 2+ years, revived in 6 weeks
6. Residencia Sotogrande — 1,500m² estate, ongoing annual maintenance contract (not a one-off transformation)
Full original task/action/result detail lives in the conversation history; site copy is intentionally condensed to fit the portfolio panel's minimal overlay format.

## Output
Single `index.html`, Tailwind via CDN, all styles inline/embedded, mobile-first, served from localhost via serve.mjs, verified via screenshot.mjs against the reference (2+ comparison rounds).
