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
        const detailFields = (form.dataset.detailFields || "")
            .split(",")
            .map((name) => name.trim())
            .filter(Boolean);

        if (!input || !results || !endpoint || !target) return;

        let controller = null;
        let selected = -1;

        const render = (items) => {
            results.replaceChildren();
            if (!items.length) {
                results.style.display = "none";
                return;
            }
            items.forEach((item, idx) => {
                const li = document.createElement("li");
                li.dataset.id = item[idField] || "";
                li.setAttribute("role", "option");
                if (detailFields.length) {
                    const label = document.createElement("span");
                    label.className = "search__result-label";
                    label.textContent = item[labelField] || "";
                    li.appendChild(label);
                    const meta = document.createElement("span");
                    meta.className = "search__result-meta";
                    meta.textContent = detailFields
                        .map((field) => item[field] || "")
                        .filter(Boolean)
                        .join(", ");
                    li.appendChild(meta);
                } else {
                    li.textContent = item[labelField] || "";
                }
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
                results.replaceChildren();
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

    // ---------------------------------------------------------------------
    // Financial analysis (company page, Financials tab)
    // ---------------------------------------------------------------------
    const analysisButton = document.getElementById("run-financial-analysis");
    const analysisResult = document.getElementById("financial-analysis-result");
    if (analysisButton && analysisResult) {
        const companiesHouseHost = "find-and-update.company-information.service.gov.uk";

        const safeDocumentUrl = (value) => {
            try {
                const url = new URL(value);
                if (url.protocol !== "https:" || url.hostname !== companiesHouseHost) return null;
                return url.href;
            } catch (err) {
                return null;
            }
        };

        const appendText = (parent, tag, className, text) => {
            const el = document.createElement(tag);
            if (className) el.className = className;
            el.textContent = text;
            parent.appendChild(el);
            return el;
        };

        const renderAnalysis = (data) => {
            analysisResult.replaceChildren();
            appendText(analysisResult, "p", null, data.summary || "");

            const periods = Array.isArray(data.periods) ? data.periods : [];
            const rows = Array.isArray(data.rows) ? data.rows : [];

            if (periods.length && rows.length) {
                const scroller = document.createElement("div");
                scroller.className = "table-scroll";
                const table = document.createElement("table");
                table.className = "data-table financial-analysis";
                const thead = document.createElement("thead");
                const headRow = document.createElement("tr");
                const corner = document.createElement("th");
                corner.scope = "col";
                corner.textContent = "Figure";
                headRow.appendChild(corner);

                periods.forEach((period) => {
                    const th = document.createElement("th");
                    th.scope = "col";
                    th.className = "num";
                    appendText(th, "span", null, period.title || "Annual accounts");
                    if (period.accounts) appendText(th, "span", "financial-analysis__sub", period.accounts);
                    if (period.filed) appendText(th, "span", "financial-analysis__filed", period.filed);
                    const href = safeDocumentUrl(period.url);
                    if (href) {
                        const link = document.createElement("a");
                        link.className = "financial-analysis__link";
                        link.href = href;
                        link.target = "_blank";
                        link.rel = "noopener nofollow";
                        link.textContent = "View filing";
                        th.appendChild(link);
                    }
                    headRow.appendChild(th);
                });
                thead.appendChild(headRow);
                table.appendChild(thead);

                const tbody = document.createElement("tbody");
                rows.forEach((row) => {
                    const tr = document.createElement("tr");
                    const th = document.createElement("th");
                    th.scope = "row";
                    th.textContent = row.label || "";
                    tr.appendChild(th);
                    const values = Array.isArray(row.values) ? row.values : [];
                    periods.forEach((_, index) => {
                        const td = document.createElement("td");
                        td.className = "num";
                        const value = values[index];
                        td.textContent = value ? value : "—";
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
                table.appendChild(tbody);
                scroller.appendChild(table);
                analysisResult.appendChild(scroller);
            }

            const notes = [];
            periods.forEach((period) => {
                if (period.warning) notes.push(period.warning);
            });
            (Array.isArray(data.footnotes) ? data.footnotes : []).forEach((note) => {
                if (note && notes.indexOf(note) === -1) notes.push(note);
            });
            if (notes.length) {
                const list = document.createElement("ul");
                list.className = "text-muted mt-2";
                notes.forEach((note) => {
                    appendText(list, "li", null, note);
                });
                analysisResult.appendChild(list);
            }
        };

        analysisButton.addEventListener("click", async () => {
            const company = analysisButton.dataset.company || "";
            if (!company || analysisButton.disabled) return;

            analysisButton.disabled = true;
            const originalLabel = analysisButton.textContent;
            analysisButton.textContent = "Reading accounts…";
            analysisResult.replaceChildren();
            appendText(
                analysisResult,
                "p",
                "text-muted",
                "Loading the latest accounts. This can take a minute."
            );

            try {
                const response = await fetch("/financial-analysis.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                    },
                    body: JSON.stringify({ company_number: company }),
                });
                const data = await response.json();
                if (!response.ok || data.error) {
                    analysisResult.replaceChildren();
                    const alert = appendText(analysisResult, "p", "alert alert-danger", data.error || "The financial analysis could not be completed.");
                    alert.setAttribute("role", "alert");
                    return;
                }
                renderAnalysis(data);
            } catch (err) {
                analysisResult.replaceChildren();
                const alert = appendText(analysisResult, "p", "alert alert-danger", "The financial analysis could not be completed.");
                alert.setAttribute("role", "alert");
            } finally {
                analysisButton.disabled = false;
                analysisButton.textContent = originalLabel;
            }
        });
    }

    // ---------------------------------------------------------------------
    // Shareholders (company page)
    // ---------------------------------------------------------------------
    const shareholderButton = document.getElementById("find-shareholders");
    const shareholderResult = document.getElementById("shareholders-result");
    if (shareholderButton && shareholderResult) {
        const appendText = (parent, tag, className, text) => {
            const el = document.createElement(tag);
            if (className) el.className = className;
            el.textContent = text;
            parent.appendChild(el);
            return el;
        };

        const renderHolders = (data) => {
            shareholderResult.replaceChildren();
            appendText(shareholderResult, "p", null, data.summary || "");
            if (data.shares_issued) {
                appendText(shareholderResult, "p", null, "Shares issued: " + data.shares_issued);
            }
            const holders = Array.isArray(data.holders) ? data.holders : [];
            if (!holders.length) return;

            const scroller = document.createElement("div");
            scroller.className = "table-scroll";
            const table = document.createElement("table");
            table.className = "data-table";
            const thead = document.createElement("thead");
            const headRow = document.createElement("tr");
            ["Shareholder", "Shares", "Percentage"].forEach((label) => {
                const th = document.createElement("th");
                th.scope = "col";
                th.textContent = label;
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            table.appendChild(thead);

            const tbody = document.createElement("tbody");
            holders.forEach((holder) => {
                const tr = document.createElement("tr");
                const shares = [holder.shares, holder.share_class].filter(Boolean).join(" ");
                [holder.name, shares, holder.percentage].forEach((value, index) => {
                    const cell = document.createElement(index === 0 ? "th" : "td");
                    if (index === 0) cell.scope = "row";
                    cell.textContent = value || "—";
                    tr.appendChild(cell);
                });
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            scroller.appendChild(table);
            shareholderResult.appendChild(scroller);
        };

        shareholderButton.addEventListener("click", async () => {
            const company = shareholderButton.dataset.company || "";
            if (!company || shareholderButton.disabled) return;

            shareholderButton.disabled = true;
            const originalLabel = shareholderButton.textContent;
            shareholderButton.textContent = "Reading filings…";
            shareholderResult.replaceChildren();
            appendText(
                shareholderResult,
                "p",
                "text-muted",
                "Looking up the shareholders. This can take a minute."
            );

            try {
                const response = await fetch("/shareholders.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                    },
                    body: JSON.stringify({ company_number: company }),
                });
                const data = await response.json();
                if (!response.ok || data.error) {
                    shareholderResult.replaceChildren();
                    const alert = appendText(shareholderResult, "p", "alert alert-danger", data.error || "The shareholders could not be read.");
                    alert.setAttribute("role", "alert");
                    return;
                }
                renderHolders(data);
            } catch (err) {
                shareholderResult.replaceChildren();
                const alert = appendText(shareholderResult, "p", "alert alert-danger", "The shareholders could not be read.");
                alert.setAttribute("role", "alert");
            } finally {
                shareholderButton.disabled = false;
                shareholderButton.textContent = originalLabel;
            }
        });
    }
})();
