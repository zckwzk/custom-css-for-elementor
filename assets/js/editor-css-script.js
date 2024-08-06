jQuery(window).on("elementor/frontend/init", function () {
  function addCustomCss(css, context) {
    if (!context) return;

    const model = context.model;
    const dataAttributes = model.get("settings").attributes;
    let customCss = "";

    // Extract and sort device values
    const deviceValue = Object.keys(dataAttributes)
      .filter((key) => key.includes("_custom_css_f_ele_value"))
      .map((key) => ({ [key]: dataAttributes[key] }))
      .sort((a, b) => {
        const keyA = Object.keys(a)[0];
        const keyB = Object.keys(b)[0];
        return b[keyB] - a[keyA];
      });

    // Build custom CSS
    deviceValue.forEach((item, index) => {
      const key = Object.keys(item)[0];
      const cssKey = key.replace("ele_value", "ele_css");
      const cssContent = dataAttributes[cssKey];
      if (index === 0) {
        customCss += cssContent;
      } else {
        customCss += ` @media (max-width: ${item[key]}px) { ${cssContent} }`;
      }
    });

    if (!customCss) return;

    const selector =
      model.get("elType") === "document"
        ? elementor.config.document.settings.cssWrapperSelector
        : `.elementor-${modelData.postID} .elementor-element.elementor-element-${model.get("id")}`;

    return DOMPurify.sanitize(
      css + customCss.replaceAll("selector", selector),
      { CSS: true },
    );
  }

  if (typeof elementor !== "undefined") {
    elementor.hooks.addFilter("editor/style/styleText", addCustomCss);
  }
});

jQuery(window).on("elementor:init", function () {
  elementor.hooks.addAction(
    "panel/open_editor/widget",
    function (panel, model, view) {
      const controlName = "_custom_css_f_ele_value_";
      const controlSelector = `input[data-setting*="${controlName}"]`;

      setTimeout(function () {
        jQuery(controlSelector).attr("readonly", true);
      }, 500);
    },
  );
});

