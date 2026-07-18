<?php
/**
 * Shared Head Inclusion.
 * Provides Google Fonts, Tailwind CSS with the Stitch Social Mission Control theme configuration,
 * and Material Symbols Outlined stylesheet.
 */
?>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>

<!-- Fonts -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@600;700&family=IBM+Plex+Mono:wght@500;600&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@100..900&display=swap" rel="stylesheet"/>

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

<!-- Theme Config -->
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "surface-bright": "#f8f9fc",
                    "surface-container-lowest": "#ffffff",
                    "tertiary-fixed-dim": "#bdc6dd",
                    "tertiary": "#394254",
                    "on-background": "#191c1e",
                    "surface-variant": "#e1e2e5",
                    "inverse-surface": "#2e3133",
                    "surface-dim": "#d9dadd",
                    "secondary-fixed": "#dbe2fb",
                    "primary-fixed": "#dfe0ff",
                    "surface": "#f8f9fc",
                    "primary-fixed-dim": "#bcc2ff",
                    "surface-container-highest": "#e1e2e5",
                    "primary": "#2031a9",
                    "secondary-container": "#d9dff8",
                    "on-tertiary-fixed": "#121c2c",
                    "secondary-fixed-dim": "#bfc6de",
                    "on-tertiary-container": "#c7d0e7",
                    "on-secondary-fixed-variant": "#3f465a",
                    "on-surface-variant": "#454653",
                    "error-container": "#ffdad6",
                    "surface-container": "#edeef1",
                    "on-error-container": "#93000a",
                    "surface-container-low": "#f2f3f6",
                    "outline": "#757685",
                    "background": "#f8f9fc",
                    "secondary": "#575e73",
                    "on-primary-fixed": "#000c62",
                    "primary-container": "#3c4cc1",
                    "surface-tint": "#4252c7",
                    "on-secondary-container": "#5b6277",
                    "on-primary": "#ffffff",
                    "on-tertiary": "#ffffff",
                    "inverse-primary": "#bcc2ff",
                    "tertiary-container": "#50596d",
                    "outline-variant": "#c6c5d6",
                    "on-primary-fixed-variant": "#2737ae",
                    "on-error": "#ffffff",
                    "on-primary-container": "#c8cdff",
                    "surface-container-high": "#e7e8eb",
                    "on-surface": "#191c1e",
                    "on-secondary-fixed": "#141b2d",
                    "on-secondary": "#ffffff",
                    "tertiary-fixed": "#d9e2fa",
                    "inverse-on-surface": "#f0f1f4",
                    "on-tertiary-fixed-variant": "#3e475a",
                    "error": "#ba1a1a"
            },
            "borderRadius": {
                    "DEFAULT": "0.25rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "full": "9999px"
            },
            "spacing": {
                    "base": "4px",
                    "sm": "8px",
                    "md": "16px",
                    "gutter": "16px",
                    "xl": "32px",
                    "xs": "4px",
                    "container-padding": "24px",
                    "lg": "24px"
            },
            "fontFamily": {
                    "body-sm": ["Inter"],
                    "display-lg": ["Space Grotesk"],
                    "display-md": ["Space Grotesk"],
                    "body-lg": ["Inter"],
                    "body-md": ["Inter"],
                    "data-label": ["IBM Plex Mono"],
                    "headline-sm": ["Space Grotesk"],
                    "data-metric": ["IBM Plex Mono"],
                    "display-lg-mobile": ["Space Grotesk"]
            },
            "fontSize": {
                    "body-sm": ["13px", {"lineHeight": "18px", "fontWeight": "400"}],
                    "display-lg": ["36px", {"lineHeight": "44px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "display-md": ["28px", {"lineHeight": "36px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                    "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "500"}],
                    "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                    "data-label": ["12px", {"lineHeight": "16px", "letterSpacing": "0.02em", "fontWeight": "500"}],
                    "headline-sm": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                    "data-metric": ["14px", {"lineHeight": "20px", "fontWeight": "600"}],
                    "display-lg-mobile": ["28px", {"lineHeight": "34px", "fontWeight": "700"}]
            }
          },
        },
      }
</script>

<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            line-height: 1;
            text-transform: none;
            letter-spacing: normal;
            word-wrap: normal;
            white-space: nowrap;
            direction: ltr;
        }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
