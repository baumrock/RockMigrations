/**
 * This file is loaded in the PW backend
 */
(() => {
  // add tooltips in the backend
  $(document).ready(() => {
    let addTooltip = function (el) {
      let name = el.name;
      if (name == "templateLabel") name = "label";
      else if (name == "field_label") name = "label";
      else if (name == "asmSelect0") return;
      let code = '"' + name + '" => "' + el.value + '",';
      $(el).attr("title", code + " (shift-click to copy)");
      $(el).attr("rockmigrations-code", code);
      UIkit.tooltip(el);
      // console.log("added tooltip", el, el.value);
    };
    $(
      ".rm-hints input[name], .rm-hints textarea[name], .rm-hints select[name]"
    ).each((i, el) => {
      // don't add hints on asm select fields
      // this is to fix this issue: https://processwire.com/talk/topic/29462-no-title-field-with-add-new-page-in-pw-anymore-after-hidetitle-true/?do=findComment&comment=238531
      if (el.closest(".InputfieldAsmSelect")) return;
      addTooltip(el);
    });

    // on shift-click copy the attribute "rockmigrations-code" of the clicked element to the clipboard
    $(document).on("click", "[rockmigrations-code]", function (e) {
      if (!e.shiftKey) return;
      addTooltip(e.target);
      const codeToCopy = $(this).attr("rockmigrations-code");
      copyToClipboard(codeToCopy);
    });
  });

  // copy page id and template name on click (if tweaks are enabled)
  $(document).on("mousedown", ".PageListTemplate, .PageListId", (e) => {
    if (!e.shiftKey) return;
    let contentToCopy = $(e.target).text().trim();
    if (contentToCopy.startsWith("#")) {
      contentToCopy = contentToCopy.substring(1);
    }
    if (contentToCopy.startsWith("[") && contentToCopy.endsWith("]")) {
      contentToCopy = contentToCopy.substring(1, contentToCopy.length - 1);
    }
    copyToClipboard(contentToCopy);
  });

  // Utility function to copy text to clipboard and show notification
  function copyToClipboard(text) {
    const textarea = document.createElement("textarea");
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand("copy");
    document.body.removeChild(textarea);
    UIkit.notification("Copied: " + text, { status: "success" });
  }
})();
