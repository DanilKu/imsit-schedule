(function() {
  function run() {
    var container = document.getElementById('bgEffect');
    if (!container) return;

    var dotsEl = document.getElementById('bgEffectDots');
    var stripsEl = document.getElementById('bgEffectStrips');
    var linesSvg = document.getElementById('bgEffectLines');
    if (!dotsEl || !stripsEl || !linesSvg) return;

    var dots = [];
    var cursor = { x: -9999, y: -9999 };
    var raf = null;
    var maxDist = 160;
    var cursorRadius = 320;
    var lineOpacity = 0.4;

    function rand(min, max) {
      return min + Math.random() * (max - min);
    }

    function createDots() {
      for (var i = 0; i < 32; i++) {
        var d = document.createElement('div');
        d.className = 'bg-dot';
        d.style.left = rand(0, 100) + '%';
        d.style.top = rand(0, 100) + '%';
        d.style.animationDelay = rand(0, 6) + 's';
        d.style.animationDuration = (8 + rand(0, 6)) + 's';
        dotsEl.appendChild(d);
        dots.push({ el: d, x: 0, y: 0 });
      }
    }

    function createStrips() {
      for (var i = 0; i < 16; i++) {
        var s = document.createElement('div');
        s.className = 'bg-strip';
        s.style.left = rand(0, 100) + '%';
        s.style.top = rand(0, 100) + '%';
        s.style.transform = 'rotate(' + rand(0, 360) + 'deg)';
        s.style.width = (50 + rand(0, 80)) + 'px';
        stripsEl.appendChild(s);
      }
    }

    function updateDotPositions() {
      for (var i = 0; i < dots.length; i++) {
        var rect = dots[i].el.getBoundingClientRect();
        dots[i].x = rect.left + rect.width / 2;
        dots[i].y = rect.top + rect.height / 2;
      }
    }

    function drawLines() {
      updateDotPositions();
      var cx = cursor.x;
      var cy = cursor.y;
      var inRange = [];
      for (var i = 0; i < dots.length; i++) {
        var dx = dots[i].x - cx;
        var dy = dots[i].y - cy;
        if (dx * dx + dy * dy < cursorRadius * cursorRadius) inRange.push(i);
      }
      var lines = [];
      for (var a = 0; a < inRange.length; a++) {
        for (var b = a + 1; b < inRange.length; b++) {
          var i = inRange[a];
          var j = inRange[b];
          var ddx = dots[i].x - dots[j].x;
          var ddy = dots[i].y - dots[j].y;
          var dist = Math.sqrt(ddx * ddx + ddy * ddy);
          if (dist < maxDist) {
            var op = lineOpacity * (1 - dist / maxDist);
            lines.push({ x1: dots[i].x, y1: dots[i].y, x2: dots[j].x, y2: dots[j].y, opacity: op });
          }
        }
      }
      var w = window.innerWidth;
      var h = window.innerHeight;
      linesSvg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
      linesSvg.setAttribute('width', w);
      linesSvg.setAttribute('height', h);
      linesSvg.innerHTML = '';
      lines.forEach(function(l) {
        var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', l.x1);
        line.setAttribute('y1', l.y1);
        line.setAttribute('x2', l.x2);
        line.setAttribute('y2', l.y2);
        line.setAttribute('stroke', 'rgba(220,200,255,' + l.opacity + ')');
        line.setAttribute('stroke-width', '1.5');
        line.setAttribute('stroke-linecap', 'round');
        linesSvg.appendChild(line);
      });
    }

    function onMove(e) {
      cursor.x = e.clientX;
      cursor.y = e.clientY;
      if (!raf) {
        raf = requestAnimationFrame(function() {
          raf = null;
          drawLines();
        });
      }
    }

    function onLeave() {
      cursor.x = -9999;
      cursor.y = -9999;
      linesSvg.innerHTML = '';
    }

    function onTouch(e) {
      if (e.touches && e.touches[0]) {
        cursor.x = e.touches[0].clientX;
        cursor.y = e.touches[0].clientY;
        if (!raf) {
          raf = requestAnimationFrame(function() {
            raf = null;
            drawLines();
          });
        }
      }
    }

    createDots();
    createStrips();
    document.addEventListener('mousemove', onMove, { passive: true });
    document.addEventListener('mouseleave', onLeave);
    document.addEventListener('touchstart', onTouch, { passive: true });
    document.addEventListener('touchmove', onTouch, { passive: true });
    document.addEventListener('touchend', onLeave);
    document.addEventListener('touchcancel', onLeave);
    window.addEventListener('resize', function() {
      onLeave();
      setTimeout(drawLines, 50);
    });
    setTimeout(drawLines, 300);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
