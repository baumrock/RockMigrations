// // fix language tabs sometimes not having the correct language
// // todo make that a selectable tweak
// $(window).on("load", () => {
//   if (typeof ProcessWire == "undefined") return;
//   if (typeof ProcessWire.config == "undefined") return;
//   if (typeof ProcessWire.config.rmUserLang == "undefined") return;
//   let lang = ProcessWire.config.rmUserLang;
//   setTimeout(() => {
//     let tabs = $(".langTab" + lang);
//     if (!tabs.length) return;
//     tabs.trigger("click");
//     console.log("LanguageTabs set via RockMigrations");
//   }, 200);
// });

// add tooltips in the backend
$(document).ready(() => {
  let addTooltip = function (el) {
    let name = el.name;
    if (name == "templateLabel") name = "label";
    else if (name == "field_label") name = "label";
    else if (name == "asmSelect0") return;
    $(el).attr("title", name + " = " + el.value);
    UIkit.tooltip(el);
    console.log("added tooltip", el, el.value);
  };
  $(
    ".rm-hints input[name], .rm-hints textarea[name], .rm-hints select[name]"
  ).each((i, el) => {
    addTooltip(el);
  });
});
