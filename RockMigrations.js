/**
 * This file is loaded in the PW backend
 */

// add tooltips in the backend
$(document).ready(() => {
  let addTooltip = function (el) {
    let name = el.name;
    if (name == "templateLabel") name = "label";
    else if (name == "field_label") name = "label";
    else if (name == "asmSelect0") return;
    $(el).attr("title", name + " = " + el.value);
    UIkit.tooltip(el);
    // console.log("added tooltip", el, el.value);
  };
  $(
    ".rm-hints input[name], .rm-hints textarea[name], .rm-hints select[name]"
  ).each((i, el) => {
    addTooltip(el);
  });
});

// copy page id and template name on click (if tweaks are enabled)
$(document).on("mousedown", ".PageListTemplate, .PageListId", (e) => {
  if (!e.shiftKey) return;
  let el = e.target;

  // trim content
  let contentToCopy = $(el).text().trim();
  if (contentToCopy.startsWith("#")) {
    contentToCopy = contentToCopy.substring(1);
  }
  if (contentToCopy.startsWith("[") && contentToCopy.endsWith("]")) {
    contentToCopy = contentToCopy.substring(1, contentToCopy.length - 1);
  }

  // copy to clipboard
  const textarea = document.createElement("textarea");
  textarea.value = contentToCopy;
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand("copy");
  document.body.removeChild(textarea);

  // show notification
  UIkit.tooltip(el).hide();
  UIkit.notification("Copied " + contentToCopy + " to clipboard", {
    status: "success",
  });
});
