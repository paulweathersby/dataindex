/* Data Index — small JS utilities (no external dependencies). */
(function () {
    "use strict";

    // ---------------------------------------------------------------------
    // Mobile nav toggle
    // ---------------------------------------------------------------------
    const navToggle = document.querySelector("[data-nav-toggle]");
    const nav = document.querySelector("[data-primary-nav]");
    if (navToggle && nav) {
        navToggle.addEventListener("click", () => {
            const open = nav.classList.toggle("is-open");
            navToggle.setAttribute("aria-expanded", open ? "true" : "false");
        });
    }

    // ---------------------------------------------------------------------
    // Tabs
    // ---------------------------------------------------------------------
    document.querySelectorAll("[data-tabs]").forEach((root) => {
        const links = root.querySelectorAll(".tabs__nav a");
        const panels = root.querySelectorAll(".tabs__panel");

        function activate(id) {
            links.forEach((a) => {
                const isActive = a.getAttribute("href") === "#" + id;
                a.classList.toggle("is-active", isActive);
                if (isActive) a.setAttribute("aria-selected", "true");
                else a.removeAttribute("aria-selected");
            });
            panels.forEach((p) => p.classList.toggle("is-active", p.id === id));
        }

        links.forEach((a) =>
            a.addEventListener("click", (e) => {
                e.preventDefault();
                const id = a.getAttribute("href").slice(1);
                activate(id);
                history.replaceState(null, "", "#" + id);
            })
        );

        const initial =
            (location.hash && document.getElementById(location.hash.slice(1)))
                ? location.hash.slice(1)
                : panels[0] && panels[0].id;
        if (initial) activate(initial);
    });

    // ---------------------------------------------------------------------
    // Autocomplete search (used on the home and VAT search pages).
    // Usage:
    //   <form data-search
    //         data-endpoint="/typeahead.php"
    //         data-target="/company/number/{id}"
    //         data-label-field="CompanyName"
    //         data-id-field="CompanyNumber">
    //     ...
    //   </form>
    // ---------------------------------------------------------------------
    document.querySelectorAll("form[data-search]").forEach((form) => {
        const input      = form.querySelector(".search__input");
        const results    = form.querySelector(".search__results");
        const endpoint   = form.dataset.endpoint;
        const target     = form.dataset.target;
        const labelField = form.dataset.labelField;
        const idField    = form.dataset.idField;

        if (!input || !results || !endpoint || !target) return;

        let controller = null;
        let selected = -1;

        const render = (items) => {
            results.innerHTML = "";
            if (!items.length) {
                results.style.display = "none";
                return;
            }
            items.forEach((item, idx) => {
                const li = document.createElement("li");
                li.textContent = item[labelField] || "";
                li.dataset.id = item[idField] || "";
                li.setAttribute("role", "option");
                li.addEventListener("click", () => navigateTo(li.dataset.id));
                if (idx === selected) li.setAttribute("aria-selected", "true");
                results.appendChild(li);
            });
            results.style.display = "block";
        };

        const navigateTo = (id) => {
            if (!id) return;
            window.location.href = target.replace(/\{[^}]+\}/, encodeURIComponent(id));
        };

        const runQuery = async (q) => {
            if (controller) controller.abort();
            controller = new AbortController();
            try {
                const res = await fetch(
                    endpoint + "?query=" + encodeURIComponent(q),
                    { signal: controller.signal, headers: { "Accept": "application/json" } }
                );
                if (!res.ok) throw new Error("HTTP " + res.status);
                const data = await res.json();
                if (Array.isArray(data)) render(data);
            } catch (err) {
                if (err.name !== "AbortError") console.warn("search:", err);
            }
        };

        let debounce;
        input.addEventListener("input", () => {
            selected = -1;
            const q = input.value.trim();
            clearTimeout(debounce);
            if (q.length < 2) {
                results.innerHTML = "";
                results.style.display = "none";
                return;
            }
            debounce = setTimeout(() => runQuery(q), 150);
        });

        input.addEventListener("keydown", (e) => {
            const items = results.querySelectorAll("li");
            if (!items.length) return;
            if (e.key === "ArrowDown") {
                e.preventDefault();
                selected = (selected + 1) % items.length;
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                selected = (selected - 1 + items.length) % items.length;
            } else if (e.key === "Enter" && selected >= 0) {
                e.preventDefault();
                navigateTo(items[selected].dataset.id);
                return;
            } else {
                return;
            }
            items.forEach((li, i) => {
                if (i === selected) li.setAttribute("aria-selected", "true");
                else li.removeAttribute("aria-selected");
            });
            items[selected] && items[selected].scrollIntoView({ block: "nearest" });
        });

        document.addEventListener("click", (e) => {
            if (!form.contains(e.target)) {
                results.style.display = "none";
            }
        });

        form.addEventListener("submit", (e) => e.preventDefault());
    });
})();
