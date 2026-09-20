window.SharedNavigation.ready.then(() => {
  "use strict";
  const id = Number(new URLSearchParams(location.search).get("id")),
    toast = (type, message) =>
      window.Notifications?.[type]?.(message) ||
      (type === "error" && alert(message));
  const $ = (selector) => document.querySelector(selector);
  function initializeShell() {
    const sidebar = $("#sidebar"),
      scrim = $("[data-sidebar-scrim]"),
      trigger = $('[aria-controls="sidebar"]');
    document.querySelectorAll("[data-sidebar-toggle]").forEach(
      (button) =>
        (button.onclick = () => {
          const opening = sidebar.classList.contains("-translate-x-full");
          sidebar.classList.toggle("-translate-x-full", !opening);
          sidebar.classList.toggle("translate-x-0", opening);
          scrim.classList.toggle("hidden", !opening);
          trigger?.setAttribute("aria-expanded", String(opening));
        }),
    );
    const menu = $("[data-account-menu]");
    document.addEventListener("click", (event) => {
      if (menu?.open && !menu.contains(event.target))
        menu.removeAttribute("open");
    });
  }
  function applyAccount(user) {
    const account = $("[data-account-menu]"),
      name = user.full_name || user.username,
      avatar = account.querySelector("summary > span:first-child");
    avatar.childNodes[0].textContent = EventForm.initials(name);
    account.querySelector("summary strong").textContent = name;
    account.querySelector("summary small").textContent = user.role;
    account.querySelector("div > div strong").textContent = name;
    account.querySelector("div > div span").textContent =
      user.email || user.username;
  }
  async function authenticate() {
    const response = await axios.get("api/auth.php?action=session");
    if (
      !response.data.authenticated ||
      response.data.user?.role !== "SBO Adviser"
    ) {
      location.replace("./");
      throw new Error("Unauthorized");
    }
    applyAccount(response.data.user);
    const csrf = response.data.csrf_token,
      logout = $('form[action="api/auth.php?action=logout"]');
    logout.onsubmit = async (event) => {
      event.preventDefault();
      try {
        await axios.post(
          "api/auth.php?action=logout",
          {},
          { headers: { "X-CSRF-Token": csrf } },
        );
      } finally {
        location.replace("./");
      }
    };
    return { csrf };
  }
  if (!id) {
    location.replace("pages/adviser/events.html");
    return;
  }
  initializeShell();
  authenticate()
    .then(async (session) => {
      try {
        const response = await axios.get("api/adviser-events.php", {
            params: { id },
          }),
          data = response.data.data,
          event = data.event;
        document.title = `Edit ${event.title} | CITE Events`;
        document.querySelector("[data-edit-subtitle]").textContent =
          `Update ${event.title} details.`;
        document
          .querySelectorAll("[data-cancel-link]")
          .forEach(
            (link) => (link.href = `pages/adviser/event-details.html?id=${id}`),
          );
        EventForm.mount(
          document.querySelector("[data-event-form]"),
          data.metadata,
          {
            csrf: session.csrf,
            event,
            onSaved: (result) => {
              window.Notifications?.flashNext?.(result.message || 'Event details updated successfully.');
              location.replace(
                `pages/adviser/event-details.html?id=${result.id}`,
              );
            },
          },
        );
      } catch (error) {
        toast(
          "error",
          error.response?.data?.message || "Unable to load event.",
        );
        setTimeout(() => location.replace("pages/adviser/events.html"), 800);
      }
    })
    .catch((error) => {
      if (error.message !== "Unauthorized")
        toast("error", "Unable to verify your session.");
    });
});
