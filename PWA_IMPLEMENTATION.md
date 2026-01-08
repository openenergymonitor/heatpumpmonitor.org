# PWA Implementation for HeatpumpMonitor.org

## Overview
This document describes the Progressive Web App (PWA) implementation for HeatpumpMonitor.org, enabling users to install the website as a standalone app on their devices with offline capabilities.

## What Was Implemented

### 1. Web App Manifest (`www/manifest.json`)
The manifest provides metadata about the application:
- **Name**: HeatpumpMonitor.org
- **Short Name**: HeatpumpMonitor
- **Description**: Full description of the application
- **Theme Color**: #44b3e2 (matching the site's color scheme)
- **Display Mode**: standalone (app-like experience)
- **Start URL**: / (opens at homepage)
- **Icons**: 8 different sizes (72x72 to 512x512) for various devices

### 2. Service Worker (`www/sw.js`)
The service worker provides offline functionality and caching:
- **Cache Strategy**: Network-first with cache fallback
- **Precached Assets**: 
  - Homepage (/)
  - Core CSS (Bootstrap, Font Awesome, custom styles)
  - Core JavaScript (Bootstrap)
  - App icons
- **Automatic Updates**: Service worker updates automatically when file changes
- **Offline Support**: Falls back to cached content when network is unavailable

### 3. PWA Icons
Generated multiple icon sizes from the existing `heatpumpmonitor.png` logo:
- **Standard Sizes**: 72x72, 96x96, 128x128, 144x144, 152x152, 192x192, 384x384, 512x512
- **Favicon**: 32x32 ICO format
- **Apple Touch Icon**: 180x180 for iOS devices
- **Location**: `www/theme/img/icons/`

### 4. Theme Integration (`www/theme/theme.php`)
Updated the main theme template with PWA support:
- Added manifest link in `<head>`
- Added theme-color meta tag for mobile browsers
- Added favicon and apple-touch-icon links
- Added service worker registration script at end of `<body>`
- Removed old external favicon reference

### 5. Server Configuration (`www/.htaccess`)
Updated Apache configuration to properly serve PWA files:
- Added MIME type for manifest.json: `application/manifest+json`
- Added MIME type for JavaScript: `application/javascript`
- Ensured service worker and manifest are not rewritten by routing rules

## Testing the PWA

### Prerequisites
- HTTPS is required (service workers only work over HTTPS or localhost)
- Modern browser (Chrome 40+, Firefox 44+, Safari 11.1+, Edge 17+)

### Installation Testing
1. **Desktop (Chrome/Edge)**:
   - Visit the site
   - Look for install icon in address bar (⊕ or install icon)
   - Click to install as app
   - App opens in standalone window

2. **Mobile (Android)**:
   - Visit the site in Chrome
   - Tap menu → "Add to Home screen"
   - App installs with proper icon and name
   - Opens in fullscreen mode

3. **Mobile (iOS)**:
   - Visit the site in Safari
   - Tap share button → "Add to Home Screen"
   - App installs with custom icon
   - Opens in standalone mode

### Verification in DevTools

#### Manifest Verification
1. Open Chrome DevTools (F12)
2. Go to **Application** tab
3. Click **Manifest** in left sidebar
4. Verify:
   - Manifest loads correctly
   - All icons are present and valid
   - Theme color is set
   - Start URL is correct

#### Service Worker Verification
1. Open Chrome DevTools (F12)
2. Go to **Application** tab
3. Click **Service Workers** in left sidebar
4. Verify:
   - Service worker is registered
   - Status shows "activated and running"
   - Update on reload is available

#### Offline Testing
1. Open Chrome DevTools (F12)
2. Go to **Network** tab
3. Check "Offline" checkbox
4. Refresh the page
5. Verify:
   - Page still loads (from cache)
   - Core functionality works
   - Error handling gracefully degrades

### Lighthouse Audit
Run a Lighthouse audit in Chrome DevTools:
```
1. Open DevTools (F12)
2. Click "Lighthouse" tab
3. Select "Progressive Web App" category
4. Click "Generate report"
```

Expected PWA scores:
- ✓ Installable
- ✓ PWA Optimized
- ✓ Offline capable
- ✓ Themed
- ✓ Uses HTTPS

## Browser Compatibility

### Full Support
- Chrome 40+ (Desktop & Mobile)
- Edge 17+
- Firefox 44+
- Opera 27+
- Samsung Internet 4+

### Partial Support (Install Only)
- Safari 11.1+ (iOS/macOS)
- Safari doesn't support all PWA features but allows home screen installation

### No Support (Graceful Degradation)
- Internet Explorer (no service worker support)
- Older browsers fall back to normal website functionality

## Maintenance

### Updating Icons
If the logo changes, regenerate icons using:
```bash
pip3 install Pillow
python3 /path/to/generate_pwa_icons.py
```

### Updating Cache
When significant site changes occur:
1. Update `CACHE_NAME` in `www/sw.js` (e.g., 'heatpumpmonitor-v2')
2. Update `PRECACHE_ASSETS` array if core files change
3. Service worker will auto-update on next visit

### Adding More Cached Assets
Edit `PRECACHE_ASSETS` in `www/sw.js`:
```javascript
const PRECACHE_ASSETS = [
  '/',
  '/theme/style.css',
  // Add more paths here
];
```

## Performance Benefits

### Offline Access
- Users can view previously visited pages offline
- Core app shell loads instantly from cache
- Improves reliability on unstable connections

### Faster Loading
- Cached assets load instantly
- Reduced server requests
- Better user experience

### Mobile Engagement
- Home screen icon increases visibility
- Standalone mode provides app-like experience
- Push notification support (future enhancement)

## Future Enhancements

### Recommended Next Steps
1. **Push Notifications**: Add web push notification support for updates
2. **Background Sync**: Sync data when connection is restored
3. **Advanced Caching**: Implement runtime caching strategies for API calls
4. **Offline Analytics**: Track offline usage patterns
5. **Update Notifications**: Notify users when new version is available

### Cache Strategy Options
Current: Network-first (always try network, fall back to cache)

Alternatives to consider:
- **Cache-first**: For static assets that rarely change
- **Stale-while-revalidate**: Show cached content, update in background
- **Network-only**: For dynamic data that must be fresh

## Troubleshooting

### Service Worker Not Registering
- Check HTTPS is enabled (required for service workers)
- Verify `sw.js` is in correct location (www root)
- Check browser console for errors
- Verify .htaccess allows serving .js files

### Manifest Not Loading
- Verify manifest.json is valid JSON
- Check MIME type is set correctly in .htaccess
- Use Chrome DevTools to see manifest errors

### Icons Not Displaying
- Verify icon files exist in `www/theme/img/icons/`
- Check icon paths in manifest.json
- Ensure proper permissions on icon files

### Cache Not Working
- Check service worker is activated
- Verify PRECACHE_ASSETS paths are correct
- Clear cache and re-register service worker
- Check for console errors in service worker

## Resources

### Documentation
- [MDN: Progressive Web Apps](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [web.dev: PWA Guide](https://web.dev/progressive-web-apps/)
- [Google: Service Workers](https://developers.google.com/web/fundamentals/primers/service-workers)

### Testing Tools
- [Lighthouse](https://developers.google.com/web/tools/lighthouse)
- [PWA Builder](https://www.pwabuilder.com/)
- [Chrome DevTools Application Tab](https://developer.chrome.com/docs/devtools/progressive-web-apps/)

### Standards
- [W3C Web App Manifest](https://www.w3.org/TR/appmanifest/)
- [Service Worker Specification](https://w3c.github.io/ServiceWorker/)
