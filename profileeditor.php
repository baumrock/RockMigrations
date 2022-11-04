<style>
  textarea.inputarea {
    padding: 1px !important;
    border: 0 !important;
  }

  #editorvalue {
    display: none;
  }
</style>
<div id="editor" style="height:400px;"></div>
<textarea id="editorvalue" name="code"><?= $sanitizer->entities($files->fileGetContents(__DIR__ . "/profiles/default.php")) ?></textarea>
<?php
$box = $modules->get('InputfieldCheckbox');
$box->label = " Execute code on save";
$box->name = "runcode";
echo $box->render();
?>
<link rel="stylesheet" data-name="vs/editor/editor.main" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/editor/editor.main.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/loader.min.js"></script>
<script>
  // require is provided by loader.min.js.
  require.config({
    paths: {
      'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs'
    }
  });
  require(["vs/editor/editor.main"], () => {
    let area = document.getElementById('editorvalue');
    let cache = localStorage.getItem('rockmigrations-console');
    if (cache) area.value = cache;
    let editor = monaco.editor.create(document.getElementById('editor'), {
      language: 'php',
      theme: 'vs-dark',
      value: area.value,
    });

    editor.getModel().onDidChangeContent((e) => {
      let val = editor.getValue();
      area.value = val;
      localStorage.setItem('rockmigrations-console', val);
    });
  });
</script>