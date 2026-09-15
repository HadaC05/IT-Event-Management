(() => {
  "use strict";

  const API = "api/sbo-assignments.php";
  let csrf = "";
  let tasks = [];

  document.body.insertAdjacentHTML(
    "beforeend",
    `<dialog data-scoring-admin data-modal-size="medium" data-modal-kind="form">
      <header class="flex items-start justify-between gap-5 border-b border-[#121017]/8 px-6 py-5">
        <div><p class="text-[10px] font-black uppercase tracking-[.14em] text-[#397565]">Scoring setup</p><h2 class="mt-1 text-2xl font-black">Criteria & finalization</h2><p class="mt-1.5 text-sm text-[#121017]/50">Configure the scoring sheet for an assigned event.</p></div>
        <button type="button" data-scoring-close aria-label="Close scoring setup">×</button>
      </header>
      <form data-criterion-admin>
        <div class="grid gap-4 p-6">
          <label class="grid gap-2"><span class="text-sm font-bold">Scoring responsibility</span><select class="h-11 rounded-xl border border-[#121017]/12 bg-[#F3F0E9]/35 px-3 outline-none focus:border-[#397565] focus:bg-white" name="task_id" data-scoring-task required></select></label>
          <label class="grid gap-2"><span class="text-sm font-bold">Criterion name</span><input class="h-11 rounded-xl border border-[#121017]/12 px-3 outline-none focus:border-[#397565]" name="name" placeholder="e.g. Creativity" required maxlength="80"></label>
          <div class="grid grid-cols-2 gap-3"><label class="grid gap-2"><span class="text-sm font-bold">Minimum</span><input class="h-11 rounded-xl border border-[#121017]/12 px-3 outline-none focus:border-[#397565]" type="number" name="min_points" value="0" min="0" step=".01" required></label><label class="grid gap-2"><span class="text-sm font-bold">Maximum</span><input class="h-11 rounded-xl border border-[#121017]/12 px-3 outline-none focus:border-[#397565]" type="number" name="max_points" min=".01" step=".01" required></label></div>
        </div>
        <footer class="flex flex-wrap justify-end gap-2 border-t border-[#121017]/8 px-6 py-4"><button class="min-h-11 rounded-xl border border-[#397565]/25 px-4 text-sm font-black text-[#397565]" type="button" data-reopen-sheet>Reopen finalized sheet</button><button class="min-h-11 rounded-xl px-5 text-sm font-black" data-modal-primary>Add criterion</button></footer>
      </form>
    </dialog>`,
  );

  const main = document.querySelector("[data-task-dialog] header");
  main?.insertAdjacentHTML("afterend", '<button class="mx-5 mt-4 rounded-xl border border-[#397565]/25 px-4 py-2 text-xs font-black text-[#397565]" type="button" data-scoring-open>Manage scoring criteria</button>');
  const dialog = document.querySelector("[data-scoring-admin]");
  const form = document.querySelector("[data-criterion-admin]");
  const select = document.querySelector("[data-scoring-task]");

  const load = async () => {
    const session = await axios.get("api/auth.php?action=session");
    csrf = session.data.csrf_token;
    tasks = (await axios.get(API)).data.data.tasks.filter((task) => task.responsibility === "scoring");
    select.innerHTML = tasks.length
      ? tasks.map((task) => `<option value="${task.id}">${task.event_name} · ${task.activity_name} · ${task.officer_name}</option>`).join("")
      : '<option value="">No scoring responsibility</option>';
  };

  document.querySelector("[data-scoring-open]")?.addEventListener("click", async () => {
    await load();
    dialog.showModal();
  });
  document.querySelector("[data-scoring-close]").addEventListener("click", () => dialog.close());
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    try {
      const response = await axios.post(API, { action: "criterion", ...Object.fromEntries(new FormData(form)) }, { headers: { "X-CSRF-Token": csrf } });
      Notifications.success(response.data.message);
      form.elements.name.value = "";
      form.elements.max_points.value = "";
    } catch (error) {
      Notifications.error(error.response?.data?.message || "Unable to add criterion.");
    }
  });
  document.querySelector("[data-reopen-sheet]").addEventListener("click", async () => {
    try {
      const response = await axios.post(API, { action: "reopen", task_id: select.value }, { headers: { "X-CSRF-Token": csrf } });
      Notifications.success(response.data.message);
    } catch (error) {
      Notifications.error(error.response?.data?.message || "Unable to reopen score sheet.");
    }
  });
})();
