export function integrateStudioCollector(script, runtime) {
  const start = '/* MAHANPRO_STUDIO_COLLECTOR */';
  const end = '/* MAHANPRO_STUDIO_COLLECTOR_END */';
  if (script.includes(start)) {
    const first = script.indexOf(start);
    const last = script.indexOf(end, first);
    if (last < 0) throw new Error('Existing Studio collector integration is incomplete');
    return script.slice(0, first) + start + '\n' + runtime + '\n' + script.slice(last);
  }
  const labels = 'const labels={';
  const bindings = " document.querySelectorAll('[data-section]').forEach";
  const navigation = "if(name==='brand'&&state)renderBrand();}";
  for (const marker of [labels, bindings, navigation]) {
    if (script.split(marker).length !== 2) {
      throw new Error('Studio integration point differs from the inspected version');
    }
  }
  return script
    .replace(labels, labels + "collector:'Otomatik Köy Toplama',")
    .replace(navigation, "if(name==='brand'&&state)renderBrand();document.dispatchEvent(new CustomEvent('studiosectionchange',{detail:name}));}")
    .replace(bindings, "\n/* MAHANPRO_STUDIO_COLLECTOR */\n" + runtime + "\n/* MAHANPRO_STUDIO_COLLECTOR_END */\n" + bindings);
}
