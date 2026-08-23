/*
 * Sepet — tarayıcıda tutulur (giriş gerekmez).
 * Ödeme adımında öğrenci girişi zorunludur.
 */
(function () {
  var KEY = "bmcap_cart";

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function read() {
    try {
      var raw = localStorage.getItem(KEY);
      var list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function write(list) {
    localStorage.setItem(KEY, JSON.stringify(list));
    document.dispatchEvent(new CustomEvent("bm:cart", { detail: { count: list.length } }));
  }

  function sameId(a, b) {
    return String(a) === String(b);
  }

  function courseKey(id) {
    var s = String(id == null ? "" : id);
    var m = s.match(/^course-(\d+)$/i);
    return m ? m[1] : s;
  }

  function isOwnedId(id, owned) {
    var key = courseKey(id);
    if (!key) return false;
    return (owned || []).some(function (o) {
      return courseKey(o) === key;
    });
  }

  window.BM_CART = {
    list: read,
    count: function () {
      return read().length;
    },
    has: function (id) {
      return read().some(function (x) {
        return sameId(x.id, id) || courseKey(x.id) === courseKey(id);
      });
    },
    owns: function (id, ownedIds) {
      return isOwnedId(id, ownedIds || window.BM_OWNED_COURSES || []);
    },
    pruneOwned: function (ownedIds) {
      var owned = Array.isArray(ownedIds) ? ownedIds : window.BM_OWNED_COURSES || [];
      window.BM_OWNED_COURSES = owned;
      var list = read();
      var next = list.filter(function (x) {
        return !isOwnedId(x.id, owned);
      });
      if (next.length !== list.length) {
        write(next);
      }
      document.dispatchEvent(new CustomEvent("bm:owned", { detail: { ids: owned } }));
    },
    add: function (item) {
      if (!item || item.id == null || item.id === "") return false;
      if (isOwnedId(item.id, window.BM_OWNED_COURSES || [])) return false;
      var list = read();
      if (list.some(function (x) { return sameId(x.id, item.id); })) return false;
      list.push({
        id: String(item.id),
        title: String(item.title || "Eğitim"),
        price: String(item.price || ""),
        image: String(item.image || ""),
        href: String(item.href || ""),
      });
      write(list);
      return true;
    },
    remove: function (id) {
      write(
        read().filter(function (x) {
          return !sameId(x.id, id);
        })
      );
    },
    toast: function (message, href, hrefLabel) {
      var old = document.querySelector(".cart-toast");
      if (old) old.remove();
      var el = document.createElement("div");
      el.className = "cart-toast";
      el.innerHTML =
        '<i class="fa-solid fa-circle-check"></i><span>' +
        esc(message) +
        "</span>" +
        (href
          ? '<a href="' + esc(href) + '">' + esc(hrefLabel || "Sepete git") + "</a>"
          : "");
      document.body.appendChild(el);
      setTimeout(function () {
        el.classList.add("is-out");
        setTimeout(function () {
          el.remove();
        }, 280);
      }, 4200);
    },
    checkoutUrl: function (id) {
      return "odeme.php?course=" + encodeURIComponent(id);
    },
    panelUrl: function () {
      var inOgrenci = /\/ogrenci\//.test(location.pathname);
      return inOgrenci ? "sepetim.php" : "ogrenci/sepetim.php";
    },
  };
})();
