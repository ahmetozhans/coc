export function editsFromDiff(diff) {
  const edits = [];
  let entry;
  for (const line of diff.split('\n')) {
    if (line.startsWith('@@ ')) {
      entry = {old: '', new: ''};
      edits.push(entry);
    } else if (entry && line.startsWith(' ')) {
      entry.old += line.slice(1) + '\n';
      entry.new += line.slice(1) + '\n';
    } else if (entry && line.startsWith('-')) {
      entry.old += line.slice(1) + '\n';
    } else if (entry && line.startsWith('+')) {
      entry.new += line.slice(1) + '\n';
    }
  }
  return edits;
}

export function applyTextEdits(current, edits) {
  for (const edit of edits) {
    const occurrences = value => value ? current.split(value).length - 1 : 0;
    const oldCount = occurrences(edit.old);
    const newCount = occurrences(edit.new);
    if (newCount === 1 && oldCount === 0) continue;
    if (oldCount !== 1 || newCount > 0) throw new Error('Live asset changes overlap this update');
    current = current.replace(edit.old, () => edit.new);
  }
  return current;
}
