(() => {
  "use strict";

  const root = document.querySelector("[data-reveal-stage]");
  if (!root) return;

  const $ = (selector) => root.querySelector(selector);
  const closeButton = $("[data-reveal-close]");
  const nextButton = $("[data-reveal-next]");
  const replayButton = $("[data-reveal-replay]");
  const podiumHost = $("[data-reveal-podium]");
  const lowerHost = $("[data-reveal-lower]");
  const standingsHost = $("[data-reveal-standings]");
  const announcement = $("[data-reveal-announcement]");
  const state = { podium: [], rankings: [], order: [], step: -1, preview: true, event: "", previousFocus: null, previousOverflow: "" };

  const safeColor = (value) => /^#[0-9a-f]{6}$/i.test(String(value || "")) ? value : "#397565";
  const points = (value) => Number(value || 0).toFixed(2).replace(/\.00$/, "").replace(/(\.\d)0$/, "$1");

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function labelFor(rank) {
    return ({ 1: "FIRST PLACE", 2: "SECOND PLACE", 3: "THIRD PLACE" })[rank] || `RANK ${rank}`;
  }

  function isRevealed(team) {
    const position = state.rankings.indexOf(team);
    return state.order.slice(0, Math.max(0, state.step + 1)).includes(position);
  }

  function animatedPoints(node, value) {
    const target = Number(value) || 0;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) { node.textContent = points(target); return; }
    const start = performance.now();
    const frame = (now) => {
      if (!node.isConnected) return;
      const progress = Math.min(1, (now - start) / 900);
      node.textContent = points(target * (1 - (1 - progress) ** 3));
      if (progress < 1) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  }

  function createCard(team, index, revealed, justRevealed) {
    const rank = Number(team.rank) || index + 1;
    const card = element("article", `reveal-card${index === 0 ? " reveal-card--center" : ""}${revealed ? "" : " reveal-card--locked"}${justRevealed ? " reveal-card--revealed" : ""}${revealed && rank === 1 ? " reveal-card--champion" : ""}`);
    card.style.setProperty("--team-color", revealed ? safeColor(team.color) : "#536e60");
    card.append(element("span", "reveal-card__rank", labelFor(rank)));
    const crest = element("span", "reveal-card__crest", revealed ? String(team.name || "?").trim().charAt(0).toUpperCase() : "?");
    crest.setAttribute("aria-hidden", "true");
    card.append(crest);
    if (revealed) {
      card.append(element("h3", "reveal-card__name", team.name || "Unnamed tribe"));
      const score = element("strong", "reveal-card__score");
      const amount = element("span", "", justRevealed ? "0" : points(team.total_score));
      if (justRevealed) { amount.dataset.revealScore = ""; amount.dataset.value = String(team.total_score); }
      score.append(amount, document.createTextNode(" "));
      score.append(element("small", "", "PTS"));
      card.append(score);
    } else {
      card.append(element("p", "reveal-card__locked-label", "Awaiting the reveal"));
    }
    return card;
  }

  function renderPodium() {
    const displayOrder = state.podium.length === 3 ? [1, 0, 2] : [...state.podium.keys()].reverse();
    podiumHost.style.setProperty("--reveal-columns", String(state.podium.length));
    podiumHost.replaceChildren(...displayOrder.map((index) => createCard(
      state.podium[index], index, isRevealed(state.podium[index]), state.step >= 0 && state.rankings[state.order[state.step]] === state.podium[index],
    )));
  }

  function renderLower() {
    const lower = state.rankings.slice(3).reverse();
    lowerHost.hidden = lower.length === 0 || state.step >= state.order.length;
    lowerHost.replaceChildren(...lower.map((team) => {
      const revealed = isRevealed(team);
      const justRevealed = state.step >= 0 && state.rankings[state.order[state.step]] === team;
      const row = element("article", `reveal-lower-row${revealed ? " reveal-lower-row--open" : ""}${justRevealed ? " reveal-lower-row--new" : ""}`);
      row.style.setProperty("--team-color", revealed ? safeColor(team.color) : "#536e60");
      row.append(element("strong", "reveal-lower-row__rank", `#${team.rank}`));
      row.append(element("span", "reveal-lower-row__name", revealed ? team.name || "Unnamed tribe" : "? ? ?"));
      const score = element("span", "reveal-lower-row__score", revealed ? " pts" : "SEALED");
      if (revealed) {
        const amount = element("strong", "", justRevealed ? "0" : points(team.total_score));
        if (justRevealed) { amount.dataset.revealScore = ""; amount.dataset.value = String(team.total_score); }
        score.prepend(amount);
      }
      row.append(score);
      return row;
    }));
  }

  function renderStandings() {
    const heading = element("p", "reveal-stage__standings-title", "COMPLETE EVENT STANDINGS");
    const list = element("ol", "");
    for (const team of state.rankings) {
      const row = element("li", "reveal-stage__standing");
      row.append(element("span", "reveal-stage__standing-rank", `#${team.rank}`));
      const name = element("span", "reveal-stage__standing-name");
      const dot = element("i", "");
      dot.style.setProperty("--team-color", safeColor(team.color));
      dot.setAttribute("aria-hidden", "true");
      name.append(dot, document.createTextNode(team.name || "Unnamed tribe"));
      row.append(name, element("strong", "reveal-stage__standing-points", `${points(team.total_score)} pts`));
      list.append(row);
    }
    standingsHost.replaceChildren(heading, list);
  }

  function render() {
    const finished = state.step >= state.order.length;
    root.classList.toggle("reveal-stage--complete", finished);
    const revealed = state.step >= 0 && !finished ? state.rankings[state.order[state.step]] : null;
    const rank = revealed ? Number(revealed.rank) || state.order[state.step] + 1 : null;
    const title = $("[data-reveal-title]");
    const subtitle = $("[data-reveal-subtitle]");
    const progress = $("[data-reveal-progress]");
    if (finished) {
      title.textContent = "The final standings.";
      subtitle.textContent = "Every point has brought us here. Congratulations to every tribe.";
      progress.textContent = `${state.rankings.length} tribes ranked`;
      nextButton.hidden = true;
      replayButton.hidden = false;
      standingsHost.hidden = false;
    } else if (revealed) {
      title.textContent = rank === 1 ? "Your champion." : rank <= 3 ? `${labelFor(rank).toLowerCase().replace(/^./, (letter) => letter.toUpperCase())}.` : `Rank #${rank} revealed.`;
      subtitle.textContent = rank === 1 ? "A round of applause for the tribe at the top." : rank <= 3 ? "The podium is taking shape." : "The field narrows. Who will claim the podium?";
      progress.textContent = `${state.step + 1} of ${state.order.length} revealed`;
      nextButton.textContent = state.step === state.order.length - 1 ? "Show final standings →" : "Reveal next tribe →";
      nextButton.hidden = false;
      replayButton.hidden = true;
      standingsHost.hidden = true;
    } else {
      title.textContent = "The stage is set.";
      subtitle.textContent = "Bring everyone together, then reveal each place at your own pace.";
      progress.textContent = "Ready when you are";
      nextButton.textContent = `Reveal rank #${state.rankings.length} →`;
      nextButton.hidden = false;
      replayButton.hidden = true;
      standingsHost.hidden = true;
    }
    renderPodium();
    renderLower();
    if (finished) renderStandings();
    root.querySelectorAll("[data-reveal-score]").forEach((node) => animatedPoints(node, node.dataset.value));
    announcement.textContent = finished ? `Full standings shown for ${state.event}.` : revealed
      ? `${labelFor(rank)}: ${revealed.name}, ${points(revealed.total_score)} points.` : `${state.event} reveal stage ready.`;
  }

  function open(data, options = {}) {
    if (!Array.isArray(data?.rankings) || data.rankings.length === 0) return false;
    state.rankings = data.rankings.filter((team) => team.has_score !== false);
    if (!state.rankings.length) return false;
    state.podium = state.rankings.slice(0, 3);
    state.order = [...state.rankings.keys()].reverse();
    state.step = -1;
    state.preview = Boolean(options.preview);
    state.event = data.selected_event?.title || "CITE Events";
    state.previousFocus = document.activeElement;
    state.previousOverflow = document.body.style.overflow;
    $("[data-reveal-event]").textContent = state.event;
    $("[data-reveal-mode]").textContent = state.preview ? "REHEARSAL · PRIVATE PREVIEW" : "LIVE PRESENTATION · STUDENTS CAN VIEW";
    root.hidden = false;
    document.body.style.overflow = "hidden";
    render();
    root.scrollTop = 0;
    root.focus({ preventScroll: true });
    return true;
  }

  function close() {
    if (root.hidden) return;
    root.hidden = true;
    document.body.style.overflow = state.previousOverflow;
    state.previousFocus?.focus?.();
  }

  function onKeydown(event) {
    if (root.hidden) return;
    if (event.key === "Escape") { event.preventDefault(); close(); return; }
    if (event.key !== "Tab") return;
    const controls = [closeButton, nextButton, replayButton].filter((button) => !button.hidden);
    const first = controls[0], last = controls[controls.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === root)) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }

  closeButton.addEventListener("click", close);
  nextButton.addEventListener("click", () => { state.step += 1; render(); });
  replayButton.addEventListener("click", () => { state.step = -1; render(); nextButton.focus(); });
  document.addEventListener("keydown", onKeydown);
  window.LeaderboardReveal = { open, close };
})();
