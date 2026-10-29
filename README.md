# Worldwide Charters Private Limited — Website

A four-page corporate website built with **plain HTML, CSS and JavaScript** plus **Bootstrap 5.3** (included locally). There's no framework, build step or CMS: the files in this folder are the website. The only server-side file is a small PHP script that emails enquiry-form submissions.

| Page | File |
|---|---|
| Home | `index.html` |
| About Us | `about-us.html` |
| Services (all six services on one page) | `services.html` |
| Contact Us + enquiry form | `contact-us.html` |
| Not found (not in navigation, noindex) | `404.html` |

Service sections on `services.html`: `#charter-operations`, `#aircraft-leasing`, `#fleet-management`, `#air-transportation`, `#aircraft-sales-acquisition`, `#aviation-infrastructure`, `#faq`.
To preselect a form category, link to `contact-us.html?service=<section-id>#enquiry` (also `partnership` or `general`).

---

## ⚠️ Before launch (required)

1. **Set the real domain.** The pages use the placeholder `https://www.example.com` for canonical URLs, social-sharing tags and structured data. Replace it everywhere (in all `*.html`, `sitemap.xml` and `robots.txt`) with the final domain, for example with your editor's find and replace across the folder.
2. **Configure the enquiry form** (see [Enquiry form](#enquiry-form)). Until this is done the form shows an error message and sends nothing. It never displays a fake success message.
3. **Get the client to confirm the content items below.**

### Content that needs client confirmation
- **Address.** The site uses the spelling on the company letterhead: *L-304, AWHO Vijay Ratan Vihar, Sector 15 Part 2, Gurgaon, Sadar Bazar, Haryana – 122001*. The brief wrote "AHWO", but the profile says "AWHO". The address is shown as text only, with no map.
- **Email.** `worldwidecharterservice@gmail.com`, taken from the profile.
- **Director profiles.** These are short, text-only summaries reworded from the company profile. There are no photos and no DIN numbers. They need the directors' approval.
- **CIN** `U77307HR2026PTC149008` appears in the footer. Remove it if the client prefers.
- **Not published, on purpose:** the establishment date, any phone number, office hours, WhatsApp, social profiles, privacy policy and terms links. Add these only once the client supplies and approves them.

---

## Folder structure

```
index.html  about-us.html  services.html  contact-us.html  404.html
assets/css/bootstrap.min.css      Bootstrap 5.3.3 (unmodified)
assets/css/style.css              Brand theme + components (edit this for design changes)
assets/js/bootstrap.bundle.min.js
assets/js/main.js                 Header, mobile menu, services-page navigation
assets/js/contact.js              Form preselection, validation, submission
assets/img/                       Photos (WebP, several sizes) + icons.svg
assets/brand/                     Logo (original artwork, transparent background)
assets/fonts/                     Inter + Manrope (self-hosted)
api/enquiry.php                   Form handler
api/config.sample.php             Form configuration template (no secrets)
favicon.ico, favicon-32.png, icon-192.png, icon-512.png, apple-touch-icon.png
og-image.jpg, site.webmanifest, robots.txt, sitemap.xml, .htaccess
```

**Shared header and footer.** Plain HTML has no includes, so the header and footer are repeated in each page between `<!-- SHARED HEADER -->` and `<!-- SHARED FOOTER -->` comments. When you change one, change it on all five pages. In the header, only the `active` class and `aria-current="page"` differ between pages.

**Cache busting.** CSS and JS links end in `?v=1.0.0`. Increase this number on every page after you edit `style.css`, `main.js` or `contact.js`.

---

## Preview locally

- **Quick look:** open `index.html` in a browser. Pages, links and styles all work.
- **Full test, including the form:** run a local PHP server from this folder and open http://localhost:8080:
  ```bash
  php -S localhost:8080 -t .
  ```

---

## Deploy (Hostinger or any Apache + PHP host)

1. Upload everything in this folder **except `README.md`** into `public_html/`.
2. Make sure `.htaccess` is uploaded. Hidden files are sometimes skipped by FTP clients. It provides HTTPS redirection, the 404 page, compression, caching and security headers.
3. Enable SSL for the domain in hPanel.
4. Configure the form (below), then send a test enquiry.
5. Submit `https://<domain>/sitemap.xml` in Google Search Console.

---

## Enquiry form

`contact-us.html` posts to `api/enquiry.php`, which:
- validates every field on the server and accepts only the 8 approved categories;
- blocks spam with a hidden honeypot field, a 3-second minimum fill time, a maximum of 3 links per message, a same-origin check, and a limit of 5 submissions per 15 minutes per IP address;
- strips control characters and encodes display names, so form input cannot inject email headers;
- emails the enquiry to the company inbox, with **Reply-To set to the visitor's address**.

Submissions aren't stored anywhere.

### Configuration (no secrets are stored in these files)

1. Create a mailbox on the website's domain in hPanel → Emails, for example `enquiries@yourdomain.com`. The sender must be on your own domain so the email isn't rejected or marked as spam.
2. Copy `api/config.sample.php` to **`wwc-config.php` in the folder above `public_html`**, so it can never be downloaded, and fill it in:

```php
return [
    'to_email'    => 'worldwidecharterservice@gmail.com',
    'from_email'  => 'enquiries@yourdomain.com',
    'from_name'   => 'Worldwide Charters Website',
    'transport'   => 'smtp',
    'smtp_host'   => 'smtp.hostinger.com',
    'smtp_port'   => 465,
    'smtp_secure' => 'ssl',          // or 587 + 'tls'
    'smtp_user'   => 'enquiries@yourdomain.com',
    'smtp_pass'   => '••••••••',
];
```

You can use environment variables instead, if your host supports them. They override the file: `WWC_TO_EMAIL`, `WWC_FROM_EMAIL`, `WWC_FROM_NAME`, `WWC_TRANSPORT`, `WWC_SMTP_HOST`, `WWC_SMTP_PORT`, `WWC_SMTP_SECURE`, `WWC_SMTP_USER`, `WWC_SMTP_PASS`, `WWC_RATE_LIMIT_DIR`.

If spam gets past these controls later, add Cloudflare Turnstile or Google reCAPTCHA.

---

## Test report (28 Oct 2026)

| Area | Result |
|---|---|
| HTML validation | ✅ 0 errors on all 5 pages |
| Internal links, section anchors, images, icons, one `<h1>` per page | ✅ all resolve |
| Sideways scrolling, console errors, broken images at 320, 375, 390, 430, 768, 1024, 1366, 1440 and 1920px | ✅ none |
| Accessibility (axe-core, WCAG 2.2 AA), mobile and desktop | ✅ 0 violations |
| Mobile menu, active page highlight, service sections not hidden under the sticky header | ✅ |
| FAQ accordion | ✅ |
| Form: validation messages, category preselection, loading state, success message, no-JavaScript fallback | ✅ |
| Server: field validation, honeypot, time trap, rate limit, cross-origin block, header-injection attempt | ✅ |
| Email delivery | ✅ against a local test mail server. ⚠️ **Not yet tested with real Hostinger email.** Send a test enquiry after configuring. |

After launch, check page speed with PageSpeed Insights, and structured data at https://validator.schema.org.

---

## Images and licences

- **Logo:** the client-supplied file, with only the scan border cropped off and the white background made transparent. The artwork is not redrawn or recoloured.
- **Photography:** [Pexels](https://www.pexels.com/license/) (free for commercial use, no attribution required). Photo IDs: 14983522, 28776071, 23124351, 30773896, 31561265, 12639108, 10875890, 2382848, 18459049. These are generic stock aviation images, not the company's own aircraft or facilities. Replace them with the client's photography when available.
- **Icons:** Bootstrap Icons (MIT). **Fonts:** Inter and Manrope (SIL OFL).
