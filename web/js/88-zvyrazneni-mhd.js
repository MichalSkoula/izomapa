  // Zvýraznění MHD (přepínač „Zvýraznit MHD“): nad každým vozem MHD bublina s číslem linky v barvě typu
  // (autobus modře, tramvaj červeně, vlak zeleně) a pod ní pulzující kroužek. Vidět i při oddálení, kdy se vozidla nekreslí,
  // a i za budovou. Kliknutím na bublinu se vůz začne sledovat (MHD.drawn). Polohy sbírá smyčka MHD v 89-kresleni.js.
  const MHD_HL = { pts: [], img: new Map() };
  const mhdHl = () => MHD.on && $('l-mhdhl').checked;
  { const t = setInterval(() => { if (MHD.on) { $('l-mhdhl-w').hidden = false; clearInterval(t); } }, 500); setTimeout(() => clearInterval(t), 60000); }
  try { if (localStorage.getItem('izomapa-mhdhl') === '1') $('l-mhdhl').checked = true; } catch (e) {}
  $('l-mhdhl').addEventListener('change', (e) => { try { localStorage.setItem('izomapa-mhdhl', e.target.checked ? '1' : '0'); } catch (er) {} });
  const MHD_HL_BARVA = (typ) => (typ === 0 ? '#c4352b' : typ === 2 ? '#2f7d4f' : '#1f6fb2');
  function mhdHlBublina(line, typ, dpr) {                  // obrázek bubliny (ve fyzických pixelech), střed špičky dole uprostřed
    const key = `${line}|${typ}|${dpr}`; let c = MHD_HL.img.get(key); if (c) return c;
    if (MHD_HL.img.size > 300) MHD_HL.img.clear();
    const font = '700 14px "Atkinson Hyperlegible", system-ui, sans-serif';
    MER.font = font; const tw = Math.max(22, Math.ceil(MER.measureText(line).width) + 12), th = 20, sp = 6;   // šířka, výška, špička
    c = document.createElement('canvas'); c.width = Math.ceil((tw + 6) * dpr); c.height = Math.ceil((th + sp + 6) * dpr); c.W = tw + 6; c.H = th + sp + 6;
    const g = c.getContext('2d'); g.scale(dpr, dpr); g.translate(2, 2);
    const tvar = (d) => { g.beginPath(); g.moveTo(d, d); g.lineTo(tw - d, d); g.lineTo(tw - d, th - d); g.lineTo(tw / 2 + sp - d, th - d); g.lineTo(tw / 2, th + sp - d * 1.6); g.lineTo(tw / 2 - sp + d, th - d); g.lineTo(d, th - d); g.closePath(); };
    g.fillStyle = 'rgba(0,0,0,.35)'; g.save(); g.translate(2, 2); tvar(0); g.fill(); g.restore();   // stín jako u panelu
    g.fillStyle = '#16191f'; tvar(0); g.fill();
    g.fillStyle = MHD_HL_BARVA(typ); tvar(1.5); g.fill();
    g.fillStyle = '#fff'; g.font = font; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillText(line, tw / 2, th / 2 + 0.5);
    MHD_HL.img.set(key, c);
    return c;
  }
  function mhdHlDraw(now, dpr) {                           // nakreslí sesbírané bubliny (CSS pixely) a vyprázdní seznam
    const P = MHD_HL.pts; MHD_HL.pts = [];
    if (!P.length || !mhdHl()) return;
    P.sort((a, b) => a[1] - b[1]);                         // vzdálenější (výš na obrazovce) dřív
    const f = (now % 1600) / 1600;
    ctx.save();
    for (const [x, y, line, typ] of P) {                   // pulzující kroužek kolem vozu
      ctx.globalAlpha = 0.9 * (1 - f); ctx.strokeStyle = MHD_HL_BARVA(typ); ctx.lineWidth = 2.5;
      ctx.beginPath(); ctx.arc(x, y, 5 + 13 * f, 0, Math.PI * 2); ctx.stroke();
    }
    ctx.globalAlpha = 1; ctx.setTransform(1, 0, 0, 1, 0, 0);
    for (const [x, y, line, typ, i] of P) {
      const c = mhdHlBublina(line, typ, dpr), bx = x - c.W / 2 + 1, by = y - 10 - c.H;   // špička 10 px nad vozem
      ctx.drawImage(c, Math.round(bx * dpr), Math.round(by * dpr));
      MHD.drawn.push([Math.round(x), Math.round(by + 12), i]);                          // klik na bublinu = sledovat vůz
    }
    ctx.restore();
  }
