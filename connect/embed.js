/* Uraio Connect — plain JavaScript loader for non-CMS websites.
 * Usage:
 *   <div class="uraio-widget" data-widget="menu" data-token="YOUR_EMBED_TOKEN"></div>
 *   <script src="https://create.uraio.com/connect/embed.js" defer></script>
 * Optional: data-height="480" overrides the default height for the widget.
 */
(function () {
  'use strict';

  var widgets = document.querySelectorAll('.uraio-widget[data-widget][data-token]');
  if (!widgets.length) {
    return;
  }

  var script = document.currentScript || (function () {
    var scripts = document.getElementsByTagName('script');
    return scripts[scripts.length - 1];
  })();
  var base = new URL(script.src, window.location.href);
  var apiBase = base.pathname.replace(/embed\.js$/, '');

  // Sensible default heights per widget type.
  var defaultHeights = {
    menu: 560,
    products: 560,
    hours: 320,
    contact: 420,
    booking: 480,
    reviews: 520,
    whatsapp: 72
  };

  widgets.forEach(function (el) {
    var widget = el.getAttribute('data-widget');
    var token = el.getAttribute('data-token');
    var height = el.getAttribute('data-height') || String(defaultHeights[widget] || 420);

    var iframe = document.createElement('iframe');
    iframe.loading = 'lazy';
    iframe.src = (function () {
      var url = new URL(apiBase + 'embed.php', base.origin);
      url.searchParams.set('t', token);
      url.searchParams.set('w', widget);
      return url.toString();
    })();
    iframe.setAttribute('width', '100%');
    iframe.setAttribute('height', height);
    iframe.setAttribute('frameborder', '0');
    iframe.setAttribute('scrolling', 'no');
    iframe.style.cssText = 'border:0;border-radius:16px;display:block;width:100%;background:#fff;';
    iframe.title = widget;

    el.textContent = '';
    el.appendChild(iframe);
  });
})();
