(function () {
  var e,
    t,
    n,
    r,
    i = [].slice,
    s = {}.hasOwnProperty,
    o = function (e, t) {
      function r() {
        this.constructor = e;
      }
      for (var n in t) s.call(t, n) && (e[n] = t[n]);
      return (
        (r.prototype = t.prototype),
        (e.prototype = new r()),
        (e.__super__ = t.prototype),
        e
      );
    },
    u = function (e, t) {
      return function () {
        return e.apply(t, arguments);
      };
    },
    a =
      [].indexOf ||
      function (e) {
        for (var t = 0, n = this.length; t < n; t++)
          if (t in this && this[t] === e) return t;
        return -1;
      };
  (t = window.Morris = {}),
    (e = jQuery),
    (t.EventEmitter = (function () {
      function e() {}
      return (
        (e.prototype.on = function (e, t) {
          return (
            this.handlers == null && (this.handlers = {}),
            this.handlers[e] == null && (this.handlers[e] = []),
            this.handlers[e].push(t),
            this
          );
        }),
        (e.prototype.fire = function () {
          var e, t, n, r, s, o, u;
          (n = arguments[0]),
            (e = 2 <= arguments.length ? i.call(arguments, 1) : []);
          if (this.handlers != null && this.handlers[n] != null) {
            (o = this.handlers[n]), (u = []);
            for (r = 0, s = o.length; r < s; r++)
              (t = o[r]), u.push(t.apply(null, e));
            return u;
          }
        }),
        e
      );
    })()),
    (t.commas = function (e) {
      var t, n, r, i;
      return e != null
        ? ((r = e < 0 ? "-" : ""),
          (t = Math.abs(e)),
          (n = Math.floor(t).toFixed(0)),
          (r += n.replace(/(?=(?:\d{3})+$)(?!^)/g, ",")),
          (i = t.toString()),
          i.length > n.length && (r += i.slice(n.length)),
          r)
        : "-";
    }),
    (t.pad2 = function (e) {
      return (e < 10 ? "0" : "") + e;
    }),
    (t.Grid = (function (n) {
      function r(t) {
        var n = this;
        typeof t.element == "string"
          ? (this.el = e(document.getElementById(t.element)))
          : (this.el = e(t.element));
        if (this.el == null || this.el.length === 0)
          throw new Error("Graph container element not found");
        this.el.css("position") === "static" &&
          this.el.css("position", "relative"),
          (this.options = e.extend(
            {},
            this.gridDefaults,
            this.defaults || {},
            t
          )),
          typeof this.options.units == "string" &&
            (this.options.postUnits = t.units),
          (this.raphael = new Raphael(this.el[0])),
          (this.elementWidth = null),
          (this.elementHeight = null),
          (this.dirty = !1),
          this.init && this.init(),
          this.setData(this.options.data),
          this.el.bind("mousemove", function (e) {
            var t;
            return (
              (t = n.el.offset()),
              n.fire("hovermove", e.pageX - t.left, e.pageY - t.top)
            );
          }),
          this.el.bind("mouseout", function (e) {
            return n.fire("hoverout");
          }),
          this.el.bind("touchstart touchmove touchend", function (e) {
            var t, r;
            return (
              (r =
                e.originalEvent.touches[0] ||
                e.originalEvent.changedTouches[0]),
              (t = n.el.offset()),
              n.fire("hover", r.pageX - t.left, r.pageY - t.top),
              r
            );
          }),
          this.el.bind("click", function (e) {
            var t;
            return (
              (t = n.el.offset()),
              n.fire("gridclick", e.pageX - t.left, e.pageY - t.top)
            );
          }),
          this.postInit && this.postInit();
      }
      return (
        o(r, n),
        (r.prototype.gridDefaults = {
          dateFormat: null,
          axes: !0,
          grid: !0,
          gridLineColor: "#aaa",
          gridStrokeWidth: 0.5,
          gridTextColor: "#888",
          gridTextSize: 12,
          gridTextFamily: "sans-serif",
          gridTextWeight: "normal",
          hideHover: !1,
          yLabelFormat: null,
          xLabelAngle: 0,
          numLines: 5,
          padding: 25,
          parseTime: !0,
          postUnits: "",
          preUnits: "",
          ymax: "auto",
          ymin: "auto 0",
          goals: [],
          goalStrokeWidth: 1,
          goalLineColors: ["#666633", "#999966", "#cc6666", "#663333"],
          events: [],
          eventStrokeWidth: 1,
          eventLineColors: ["#005a04", "#ccffbb", "#3a5f0b", "#005502"],
        }),
        (r.prototype.setData = function (e, n) {
          var r, i, s, o, u, a, f, l, c, h, p, d, v, m;
          n == null && (n = !0), (this.options.data = e);
          if (e == null || e.length === 0) {
            (this.data = []),
              this.raphael.clear(),
              this.hover != null && this.hover.hide();
            return;
          }
          (d = this.cumulative ? 0 : null),
            (v = this.cumulative ? 0 : null),
            this.options.goals.length > 0 &&
              ((u = Math.min.apply(null, this.options.goals)),
              (o = Math.max.apply(null, this.options.goals)),
              (v = v != null ? Math.min(v, u) : u),
              (d = d != null ? Math.max(d, o) : o)),
            (this.data = function () {
              var n, r, o;
              o = [];
              for (s = n = 0, r = e.length; n < r; s = ++n)
                (f = e[s]),
                  (a = {}),
                  (a.label = f[this.options.xkey]),
                  this.options.parseTime
                    ? ((a.x = t.parseDate(a.label)),
                      this.options.dateFormat
                        ? (a.label = this.options.dateFormat(a.x))
                        : typeof a.label == "number" &&
                          (a.label = new Date(a.label).toString()))
                    : ((a.x = s),
                      this.options.xLabelFormat &&
                        (a.label = this.options.xLabelFormat(a))),
                  (c = 0),
                  (a.y = function () {
                    var e, t, n, r;
                    (n = this.options.ykeys), (r = []);
                    for (i = e = 0, t = n.length; e < t; i = ++e)
                      (p = n[i]),
                        (m = f[p]),
                        typeof m == "string" && (m = parseFloat(m)),
                        m != null && typeof m != "number" && (m = null),
                        m != null &&
                          (this.cumulative
                            ? (c += m)
                            : d != null
                            ? ((d = Math.max(m, d)), (v = Math.min(m, v)))
                            : (d = v = m)),
                        this.cumulative &&
                          c != null &&
                          ((d = Math.max(c, d)), (v = Math.min(c, v))),
                        r.push(m);
                    return r;
                  }.call(this)),
                  o.push(a);
              return o;
            }.call(this)),
            this.options.parseTime &&
              (this.data = this.data.sort(function (e, t) {
                return (e.x > t.x) - (t.x > e.x);
              })),
            (this.xmin = this.data[0].x),
            (this.xmax = this.data[this.data.length - 1].x),
            (this.events = []),
            this.options.parseTime &&
              this.options.events.length > 0 &&
              ((this.events = function () {
                var e, n, i, s;
                (i = this.options.events), (s = []);
                for (e = 0, n = i.length; e < n; e++)
                  (r = i[e]), s.push(t.parseDate(r));
                return s;
              }.call(this)),
              (this.xmax = Math.max(
                this.xmax,
                Math.max.apply(null, this.events)
              )),
              (this.xmin = Math.min(
                this.xmin,
                Math.min.apply(null, this.events)
              ))),
            this.xmin === this.xmax && ((this.xmin -= 1), (this.xmax += 1)),
            (this.ymin = this.yboundary("min", v)),
            (this.ymax = this.yboundary("max", d)),
            this.ymin === this.ymax &&
              (v && (this.ymin -= 1), (this.ymax += 1));
          if (this.options.axes === !0 || this.options.grid === !0)
            this.options.ymax === this.gridDefaults.ymax &&
            this.options.ymin === this.gridDefaults.ymin
              ? ((this.grid = this.autoGridLines(
                  this.ymin,
                  this.ymax,
                  this.options.numLines
                )),
                (this.ymin = Math.min(this.ymin, this.grid[0])),
                (this.ymax = Math.max(
                  this.ymax,
                  this.grid[this.grid.length - 1]
                )))
              : ((l = (this.ymax - this.ymin) / (this.options.numLines - 1)),
                (this.grid = function () {
                  var e, t, n, r;
                  r = [];
                  for (
                    h = e = t = this.ymin, n = this.ymax;
                    t <= n ? e <= n : e >= n;
                    h = e += l
                  )
                    r.push(h);
                  return r;
                }.call(this)));
          this.dirty = !0;
          if (n) return this.redraw();
        }),
        (r.prototype.yboundary = function (e, t) {
          var n, r;
          return (
            (n = this.options["y" + e]),
            typeof n == "string"
              ? n.slice(0, 4) === "auto"
                ? n.length > 5
                  ? ((r = parseInt(n.slice(5), 10)),
                    t == null ? r : Math[e](t, r))
                  : t != null
                  ? t
                  : 0
                : parseInt(n, 10)
              : n
          );
        }),
        (r.prototype.autoGridLines = function (e, t, n) {
          var r, i, s, o, u, a, f, l, c;
          return (
            (u = t - e),
            (c = Math.floor(Math.log(u) / Math.log(10))),
            (f = Math.pow(10, c)),
            (i = Math.floor(e / f) * f),
            (r = Math.ceil(t / f) * f),
            (a = (r - i) / (n - 1)),
            f === 1 &&
              a > 1 &&
              Math.ceil(a) !== a &&
              ((a = Math.ceil(a)), (r = i + a * (n - 1))),
            i < 0 &&
              r > 0 &&
              ((i = Math.floor(e / a) * a), (r = Math.ceil(t / a) * a)),
            a < 1
              ? ((o = Math.floor(Math.log(a) / Math.log(10))),
                (s = (function () {
                  var e, t;
                  t = [];
                  for (l = e = i; i <= r ? e <= r : e >= r; l = e += a)
                    t.push(parseFloat(l.toFixed(1 - o)));
                  return t;
                })()))
              : (s = (function () {
                  var e, t;
                  t = [];
                  for (l = e = i; i <= r ? e <= r : e >= r; l = e += a)
                    t.push(l);
                  return t;
                })()),
            s
          );
        }),
        (r.prototype._calc = function () {
          var e, t, n, r, i, s;
          (i = this.el.width()), (n = this.el.height());
          if (
            this.elementWidth !== i ||
            this.elementHeight !== n ||
            this.dirty
          ) {
            (this.elementWidth = i),
              (this.elementHeight = n),
              (this.dirty = !1),
              (this.left = this.options.padding),
              (this.right = this.elementWidth - this.options.padding),
              (this.top = this.options.padding),
              (this.bottom = this.elementHeight - this.options.padding),
              this.options.axes &&
                ((s = function () {
                  var e, n, r, i;
                  (r = this.grid), (i = []);
                  for (e = 0, n = r.length; e < n; e++)
                    (t = r[e]),
                      i.push(this.measureText(this.yAxisFormat(t)).width);
                  return i;
                }.call(this)),
                (this.left += Math.max.apply(Math, s)),
                (e = function () {
                  var e, t, n;
                  n = [];
                  for (
                    r = e = 0, t = this.data.length;
                    0 <= t ? e < t : e > t;
                    r = 0 <= t ? ++e : --e
                  )
                    n.push(
                      this.measureText(
                        this.data[r].text,
                        -this.options.xLabelAngle
                      ).height
                    );
                  return n;
                }.call(this)),
                (this.bottom -= Math.max.apply(Math, e))),
              (this.width = Math.max(1, this.right - this.left)),
              (this.height = Math.max(1, this.bottom - this.top)),
              (this.dx = this.width / (this.xmax - this.xmin)),
              (this.dy = this.height / (this.ymax - this.ymin));
            if (this.calc) return this.calc();
          }
        }),
        (r.prototype.transY = function (e) {
          return this.bottom - (e - this.ymin) * this.dy;
        }),
        (r.prototype.transX = function (e) {
          return this.data.length === 1
            ? (this.left + this.right) / 2
            : this.left + (e - this.xmin) * this.dx;
        }),
        (r.prototype.redraw = function () {
          this.raphael.clear(),
            this._calc(),
            this.drawGrid(),
            this.drawGoals(),
            this.drawEvents();
          if (this.draw) return this.draw();
        }),
        (r.prototype.measureText = function (e, t) {
          var n, r;
          return (
            t == null && (t = 0),
            (r = this.raphael
              .text(100, 100, e)
              .attr("font-size", this.options.gridTextSize)
              .attr("font-family", this.options.gridTextFamily)
              .attr("font-weight", this.options.gridTextWeight)
              .rotate(t)),
            (n = r.getBBox()),
            r.remove(),
            n
          );
        }),
        (r.prototype.yAxisFormat = function (e) {
          return this.yLabelFormat(e);
        }),
        (r.prototype.yLabelFormat = function (e) {
          return typeof this.options.yLabelFormat == "function"
            ? this.options.yLabelFormat(e)
            : "" + this.options.preUnits + t.commas(e) + this.options.postUnits;
        }),
        (r.prototype.updateHover = function (e, t) {
          var n, r;
          n = this.hitTest(e, t);
          if (n != null) return (r = this.hover).update.apply(r, n);
        }),
        (r.prototype.drawGrid = function () {
          var e, t, n, r, i, s;
          if (this.options.grid === !1 && this.options.axes === !1) return;
          (i = this.grid), (s = []);
          for (n = 0, r = i.length; n < r; n++)
            (e = i[n]),
              (t = this.transY(e)),
              this.options.axes &&
                this.drawYAxisLabel(
                  this.left - this.options.padding / 2,
                  t,
                  this.yAxisFormat(e)
                ),
              this.options.grid
                ? s.push(
                    this.drawGridLine(
                      "M" + this.left + "," + t + "H" + (this.left + this.width)
                    )
                  )
                : s.push(void 0);
          return s;
        }),
        (r.prototype.drawGoals = function () {
          var e, t, n, r, i, s, o;
          (s = this.options.goals), (o = []);
          for (n = r = 0, i = s.length; r < i; n = ++r)
            (t = s[n]),
              (e =
                this.options.goalLineColors[
                  n % this.options.goalLineColors.length
                ]),
              o.push(this.drawGoal(t, e));
          return o;
        }),
        (r.prototype.drawEvents = function () {
          var e, t, n, r, i, s, o;
          (s = this.events), (o = []);
          for (n = r = 0, i = s.length; r < i; n = ++r)
            (t = s[n]),
              (e =
                this.options.eventLineColors[
                  n % this.options.eventLineColors.length
                ]),
              o.push(this.drawEvent(t, e));
          return o;
        }),
        (r.prototype.drawGoal = function (e, t) {
          return this.raphael
            .path("M" + this.left + "," + this.transY(e) + "H" + this.right)
            .attr("stroke", t)
            .attr("stroke-width", this.options.goalStrokeWidth);
        }),
        (r.prototype.drawEvent = function (e, t) {
          return this.raphael
            .path("M" + this.transX(e) + "," + this.bottom + "V" + this.top)
            .attr("stroke", t)
            .attr("stroke-width", this.options.eventStrokeWidth);
        }),
        (r.prototype.drawYAxisLabel = function (e, t, n) {
          return this.raphael
            .text(e, t, n)
            .attr("font-size", this.options.gridTextSize)
            .attr("font-family", this.options.gridTextFamily)
            .attr("font-weight", this.options.gridTextWeight)
            .attr("fill", this.options.gridTextColor)
            .attr("text-anchor", "end");
        }),
        (r.prototype.drawGridLine = function (e) {
          return this.raphael
            .path(e)
            .attr("stroke", this.options.gridLineColor)
            .attr("stroke-width", this.options.gridStrokeWidth);
        }),
        r
      );
    })(t.EventEmitter)),
    (t.parseDate = function (e) {
      var t, n, r, i, s, o, u, a, f, l, c;
      return typeof e == "number"
        ? e
        : ((n = e.match(/^(\d+) Q(\d)$/)),
          (i = e.match(/^(\d+)-(\d+)$/)),
          (s = e.match(/^(\d+)-(\d+)-(\d+)$/)),
          (u = e.match(/^(\d+) W(\d+)$/)),
          (a = e.match(
            /^(\d+)-(\d+)-(\d+)[ T](\d+):(\d+)(Z|([+-])(\d\d):?(\d\d))?$/
          )),
          (f = e.match(
            /^(\d+)-(\d+)-(\d+)[ T](\d+):(\d+):(\d+(\.\d+)?)(Z|([+-])(\d\d):?(\d\d))?$/
          )),
          n
            ? new Date(
                parseInt(n[1], 10),
                parseInt(n[2], 10) * 3 - 1,
                1
              ).getTime()
            : i
            ? new Date(parseInt(i[1], 10), parseInt(i[2], 10) - 1, 1).getTime()
            : s
            ? new Date(
                parseInt(s[1], 10),
                parseInt(s[2], 10) - 1,
                parseInt(s[3], 10)
              ).getTime()
            : u
            ? ((l = new Date(parseInt(u[1], 10), 0, 1)),
              l.getDay() !== 4 && l.setMonth(0, 1 + ((4 - l.getDay() + 7) % 7)),
              l.getTime() + parseInt(u[2], 10) * 6048e5)
            : a
            ? a[6]
              ? ((o = 0),
                a[6] !== "Z" &&
                  ((o = parseInt(a[8], 10) * 60 + parseInt(a[9], 10)),
                  a[7] === "+" && (o = 0 - o)),
                Date.UTC(
                  parseInt(a[1], 10),
                  parseInt(a[2], 10) - 1,
                  parseInt(a[3], 10),
                  parseInt(a[4], 10),
                  parseInt(a[5], 10) + o
                ))
              : new Date(
                  parseInt(a[1], 10),
                  parseInt(a[2], 10) - 1,
                  parseInt(a[3], 10),
                  parseInt(a[4], 10),
                  parseInt(a[5], 10)
                ).getTime()
            : f
            ? ((c = parseFloat(f[6])),
              (t = Math.floor(c)),
              (r = Math.round((c - t) * 1e3)),
              f[8]
                ? ((o = 0),
                  f[8] !== "Z" &&
                    ((o = parseInt(f[10], 10) * 60 + parseInt(f[11], 10)),
                    f[9] === "+" && (o = 0 - o)),
                  Date.UTC(
                    parseInt(f[1], 10),
                    parseInt(f[2], 10) - 1,
                    parseInt(f[3], 10),
                    parseInt(f[4], 10),
                    parseInt(f[5], 10) + o,
                    t,
                    r
                  ))
                : new Date(
                    parseInt(f[1], 10),
                    parseInt(f[2], 10) - 1,
                    parseInt(f[3], 10),
                    parseInt(f[4], 10),
                    parseInt(f[5], 10),
                    t,
                    r
                  ).getTime())
            : new Date(parseInt(e, 10), 0, 1).getTime());
    }),
    (t.Hover = (function () {
      function n(n) {
        n == null && (n = {}),
          (this.options = e.extend({}, t.Hover.defaults, n)),
          (this.el = e("<div class='" + this.options["class"] + "'></div>")),
          this.el.hide(),
          this.options.parent.append(this.el);
      }
      return (
        (n.defaults = { class: "morris-hover morris-default-style" }),
        (n.prototype.update = function (e, t, n) {
          return this.html(e), this.show(), this.moveTo(t, n);
        }),
        (n.prototype.html = function (e) {
          return this.el.html(e);
        }),
        (n.prototype.moveTo = function (e, t) {
          var n, r, i, s, o, u;
          return (
            (o = this.options.parent.innerWidth()),
            (s = this.options.parent.innerHeight()),
            (r = this.el.outerWidth()),
            (n = this.el.outerHeight()),
            (i = Math.min(Math.max(0, e - r / 2), o - r)),
            t != null
              ? ((u = t - n - 10),
                u < 0 && ((u = t + 10), u + n > s && (u = s / 2 - n / 2)))
              : (u = s / 2 - n / 2),
            this.el.css({ left: i + "px", top: parseInt(u) + "px" })
          );
        }),
        (n.prototype.show = function () {
          return this.el.show();
        }),
        (n.prototype.hide = function () {
          return this.el.hide();
        }),
        n
      );
    })()),
    (t.Line = (function (e) {
      function n(e) {
        (this.hilight = u(this.hilight, this)),
          (this.onHoverOut = u(this.onHoverOut, this)),
          (this.onHoverMove = u(this.onHoverMove, this)),
          (this.onGridClick = u(this.onGridClick, this));
        if (!(this instanceof t.Line)) return new t.Line(e);
        n.__super__.constructor.call(this, e);
      }
      return (
        o(n, e),
        (n.prototype.init = function () {
          (this.pointGrow = Raphael.animation(
            { r: this.options.pointSize + 3 },
            25,
            "linear"
          )),
            (this.pointShrink = Raphael.animation(
              { r: this.options.pointSize },
              25,
              "linear"
            ));
          if (this.options.hideHover !== "always")
            return (
              (this.hover = new t.Hover({ parent: this.el })),
              this.on("hovermove", this.onHoverMove),
              this.on("hoverout", this.onHoverOut),
              this.on("gridclick", this.onGridClick)
            );
        }),
        (n.prototype.defaults = {
          lineWidth: 3,
          pointSize: 4,
          lineColors: [
            "#0b62a4",
            "#7A92A3",
            "#4da74d",
            "#afd8f8",
            "#edc240",
            "#cb4b4b",
            "#9440ed",
          ],
          pointWidths: [1],
          pointStrokeColors: ["#ffffff"],
          pointFillColors: [],
          smooth: !0,
          xLabels: "auto",
          xLabelFormat: null,
          xLabelMargin: 24,
          continuousLine: !0,
          hideHover: !1,
        }),
        (n.prototype.calc = function () {
          return this.calcPoints(), this.generatePaths();
        }),
        (n.prototype.calcPoints = function () {
          var e, t, n, r, i, s;
          (i = this.data), (s = []);
          for (n = 0, r = i.length; n < r; n++)
            (e = i[n]),
              (e._x = this.transX(e.x)),
              (e._y = function () {
                var n, r, i, s;
                (i = e.y), (s = []);
                for (n = 0, r = i.length; n < r; n++)
                  (t = i[n]), t != null ? s.push(this.transY(t)) : s.push(t);
                return s;
              }.call(this)),
              s.push(
                (e._ymax = Math.min.apply(
                  null,
                  [this.bottom].concat(
                    (function () {
                      var n, r, i, s;
                      (i = e._y), (s = []);
                      for (n = 0, r = i.length; n < r; n++)
                        (t = i[n]), t != null && s.push(t);
                      return s;
                    })()
                  )
                ))
              );
          return s;
        }),
        (n.prototype.hitTest = function (e, t) {
          var n, r, i, s, o;
          if (this.data.length === 0) return null;
          o = this.data.slice(1);
          for (n = i = 0, s = o.length; i < s; n = ++i) {
            r = o[n];
            if (e < (r._x + this.data[n]._x) / 2) break;
          }
          return n;
        }),
        (n.prototype.onGridClick = function (e, t) {
          var n;
          return (
            (n = this.hitTest(e, t)),
            this.fire("click", n, this.options.data[n], e, t)
          );
        }),
        (n.prototype.onHoverMove = function (e, t) {
          var n;
          return (n = this.hitTest(e, t)), this.displayHoverForRow(n);
        }),
        (n.prototype.onHoverOut = function () {
          if (this.options.hideHover !== !1)
            return this.displayHoverForRow(null);
        }),
        (n.prototype.displayHoverForRow = function (e) {
          var t;
          return e != null
            ? ((t = this.hover).update.apply(t, this.hoverContentForRow(e)),
              this.hilight(e))
            : (this.hover.hide(), this.hilight());
        }),
        (n.prototype.hoverContentForRow = function (e) {
          var t, n, r, i, s, o, u;
          (r = this.data[e]),
            (t = "<div class='morris-hover-row-label'>" + r.label + "</div>"),
            (u = r.y);
          for (n = s = 0, o = u.length; s < o; n = ++s)
            (i = u[n]),
              (t +=
                "<div class='morris-hover-point' style='color: " +
                this.colorFor(r, n, "label") +
                "'>\n  " +
                this.options.labels[n] +
                ":\n  " +
                this.yLabelFormat(i) +
                "\n</div>");
          return (
            typeof this.options.hoverCallback == "function" &&
              (t = this.options.hoverCallback(e, this.options, t)),
            [t, r._x, r._ymax]
          );
        }),
        (n.prototype.generatePaths = function () {
          var e, n, r, i, s;
          return (this.paths = function () {
            var o, u, f, l;
            l = [];
            for (
              r = o = 0, u = this.options.ykeys.length;
              0 <= u ? o < u : o > u;
              r = 0 <= u ? ++o : --o
            )
              (s =
                this.options.smooth === !0 ||
                ((f = this.options.ykeys[r]),
                a.call(this.options.smooth, f) >= 0)),
                (n = function () {
                  var e, t, n, s;
                  (n = this.data), (s = []);
                  for (e = 0, t = n.length; e < t; e++)
                    (i = n[e]),
                      i._y[r] !== void 0 && s.push({ x: i._x, y: i._y[r] });
                  return s;
                }.call(this)),
                this.options.continuousLine &&
                  (n = (function () {
                    var t, r, i;
                    i = [];
                    for (t = 0, r = n.length; t < r; t++)
                      (e = n[t]), e.y !== null && i.push(e);
                    return i;
                  })()),
                n.length > 1
                  ? l.push(t.Line.createPath(n, s, this.bottom))
                  : l.push(null);
            return l;
          }.call(this));
        }),
        (n.prototype.draw = function () {
          this.options.axes && this.drawXAxis(), this.drawSeries();
          if (this.options.hideHover === !1)
            return this.displayHoverForRow(this.data.length - 1);
        }),
        (n.prototype.drawXAxis = function () {
          var e,
            n,
            r,
            i,
            s,
            o,
            u,
            a,
            f,
            l,
            c = this;
          (u = this.bottom + this.options.padding / 2),
            (s = null),
            (i = null),
            (e = function (e, t) {
              var n, r, o, a, f;
              return (
                (n = c.drawXAxisLabel(c.transX(t), u, e)),
                (f = n.getBBox()),
                n.transform("r" + -c.options.xLabelAngle),
                (r = n.getBBox()),
                n.transform("t0," + r.height / 2 + "..."),
                c.options.xLabelAngle !== 0 &&
                  ((a =
                    -0.5 *
                    f.width *
                    Math.cos((c.options.xLabelAngle * Math.PI) / 180)),
                  n.transform("t" + a + ",0...")),
                (r = n.getBBox()),
                (s == null || s >= r.x + r.width || (i != null && i >= r.x)) &&
                r.x >= 0 &&
                r.x + r.width < c.el.width()
                  ? (c.options.xLabelAngle !== 0 &&
                      ((o =
                        (1.25 * c.options.gridTextSize) /
                        Math.sin((c.options.xLabelAngle * Math.PI) / 180)),
                      (i = r.x - o)),
                    (s = r.x - c.options.xLabelMargin))
                  : n.remove()
              );
            }),
            this.options.parseTime
              ? this.data.length === 1 && this.options.xLabels === "auto"
                ? (r = [[this.data[0].label, this.data[0].x]])
                : (r = t.labelSeries(
                    this.xmin,
                    this.xmax,
                    this.width,
                    this.options.xLabels,
                    this.options.xLabelFormat
                  ))
              : (r = function () {
                  var e, t, n, r;
                  (n = this.data), (r = []);
                  for (e = 0, t = n.length; e < t; e++)
                    (o = n[e]), r.push([o.label, o.x]);
                  return r;
                }.call(this)),
            r.reverse(),
            (l = []);
          for (a = 0, f = r.length; a < f; a++)
            (n = r[a]), l.push(e(n[0], n[1]));
          return l;
        }),
        (n.prototype.drawSeries = function () {
          var e, t, n, r, i, s;
          this.seriesPoints = [];
          for (
            e = t = r = this.options.ykeys.length - 1;
            r <= 0 ? t <= 0 : t >= 0;
            e = r <= 0 ? ++t : --t
          )
            this._drawLineFor(e);
          s = [];
          for (
            e = n = i = this.options.ykeys.length - 1;
            i <= 0 ? n <= 0 : n >= 0;
            e = i <= 0 ? ++n : --n
          )
            s.push(this._drawPointFor(e));
          return s;
        }),
        (n.prototype._drawPointFor = function (e) {
          var t, n, r, i, s, o;
          (this.seriesPoints[e] = []), (s = this.data), (o = []);
          for (r = 0, i = s.length; r < i; r++)
            (n = s[r]),
              (t = null),
              n._y[e] != null &&
                (t = this.drawLinePoint(
                  n._x,
                  n._y[e],
                  this.options.pointSize,
                  this.colorFor(n, e, "point"),
                  e
                )),
              o.push(this.seriesPoints[e].push(t));
          return o;
        }),
        (n.prototype._drawLineFor = function (e) {
          var t;
          t = this.paths[e];
          if (t !== null)
            return this.drawLinePath(t, this.colorFor(null, e, "line"));
        }),
        (n.createPath = function (e, n, r) {
          var i, s, o, u, a, f, l, c, h, p, d, v, m, g;
          (l = ""), n && (o = t.Line.gradients(e)), (c = { y: null });
          for (u = m = 0, g = e.length; m < g; u = ++m) {
            i = e[u];
            if (i.y != null)
              if (c.y != null)
                n
                  ? ((s = o[u]),
                    (f = o[u - 1]),
                    (a = (i.x - c.x) / 4),
                    (h = c.x + a),
                    (d = Math.min(r, c.y + a * f)),
                    (p = i.x - a),
                    (v = Math.min(r, i.y - a * s)),
                    (l +=
                      "C" +
                      h +
                      "," +
                      d +
                      "," +
                      p +
                      "," +
                      v +
                      "," +
                      i.x +
                      "," +
                      i.y))
                  : (l += "L" + i.x + "," + i.y);
              else if (!n || o[u] != null) l += "M" + i.x + "," + i.y;
            c = i;
          }
          return l;
        }),
        (n.gradients = function (e) {
          var t, n, r, i, s, o, u, a;
          (n = function (e, t) {
            return (e.y - t.y) / (e.x - t.x);
          }),
            (a = []);
          for (r = o = 0, u = e.length; o < u; r = ++o)
            (t = e[r]),
              t.y != null
                ? ((i = e[r + 1] || { y: null }),
                  (s = e[r - 1] || { y: null }),
                  s.y != null && i.y != null
                    ? a.push(n(s, i))
                    : s.y != null
                    ? a.push(n(s, t))
                    : i.y != null
                    ? a.push(n(t, i))
                    : a.push(null))
                : a.push(null);
          return a;
        }),
        (n.prototype.hilight = function (e) {
          var t, n, r, i, s;
          if (this.prevHilight !== null && this.prevHilight !== e)
            for (
              t = n = 0, i = this.seriesPoints.length - 1;
              0 <= i ? n <= i : n >= i;
              t = 0 <= i ? ++n : --n
            )
              this.seriesPoints[t][this.prevHilight] &&
                this.seriesPoints[t][this.prevHilight].animate(
                  this.pointShrink
                );
          if (e !== null && this.prevHilight !== e)
            for (
              t = r = 0, s = this.seriesPoints.length - 1;
              0 <= s ? r <= s : r >= s;
              t = 0 <= s ? ++r : --r
            )
              this.seriesPoints[t][e] &&
                this.seriesPoints[t][e].animate(this.pointGrow);
          return (this.prevHilight = e);
        }),
        (n.prototype.colorFor = function (e, t, n) {
          return typeof this.options.lineColors == "function"
            ? this.options.lineColors.call(this, e, t, n)
            : n === "point"
            ? this.options.pointFillColors[
                t % this.options.pointFillColors.length
              ] || this.options.lineColors[t % this.options.lineColors.length]
            : this.options.lineColors[t % this.options.lineColors.length];
        }),
        (n.prototype.drawXAxisLabel = function (e, t, n) {
          return this.raphael
            .text(e, t, n)
            .attr("font-size", this.options.gridTextSize)
            .attr("font-family", this.options.gridTextFamily)
            .attr("font-weight", this.options.gridTextWeight)
            .attr("fill", this.options.gridTextColor);
        }),
        (n.prototype.drawLinePath = function (e, t) {
          return this.raphael
            .path(e)
            .attr("stroke", t)
            .attr("stroke-width", this.options.lineWidth);
        }),
        (n.prototype.drawLinePoint = function (e, t, n, r, i) {
          return this.raphael
            .circle(e, t, n)
            .attr("fill", r)
            .attr("stroke-width", this.strokeWidthForSeries(i))
            .attr("stroke", this.strokeForSeries(i));
        }),
        (n.prototype.strokeWidthForSeries = function (e) {
          return this.options.pointWidths[e % this.options.pointWidths.length];
        }),
        (n.prototype.strokeForSeries = function (e) {
          return this.options.pointStrokeColors[
            e % this.options.pointStrokeColors.length
          ];
        }),
        n
      );
    })(t.Grid)),
    (t.labelSeries = function (n, r, i, s, o) {
      var u, a, f, l, c, h, p, d, v, m, g;
      (f = (200 * (r - n)) / i), (a = new Date(n)), (p = t.LABEL_SPECS[s]);
      if (p === void 0) {
        g = t.AUTO_LABEL_ORDER;
        for (v = 0, m = g.length; v < m; v++) {
          (l = g[v]), (h = t.LABEL_SPECS[l]);
          if (f >= h.span) {
            p = h;
            break;
          }
        }
      }
      p === void 0 && (p = t.LABEL_SPECS.second),
        o && (p = e.extend({}, p, { fmt: o })),
        (u = p.start(a)),
        (c = []);
      while ((d = u.getTime()) <= r) d >= n && c.push([p.fmt(u), d]), p.incr(u);
      return c;
    }),
    (n = function (e) {
      return {
        span: e * 60 * 1e3,
        start: function (e) {
          return new Date(
            e.getFullYear(),
            e.getMonth(),
            e.getDate(),
            e.getHours()
          );
        },
        fmt: function (e) {
          return "" + t.pad2(e.getHours()) + ":" + t.pad2(e.getMinutes());
        },
        incr: function (t) {
          return t.setUTCMinutes(t.getUTCMinutes() + e);
        },
      };
    }),
    (r = function (e) {
      return {
        span: e * 1e3,
        start: function (e) {
          return new Date(
            e.getFullYear(),
            e.getMonth(),
            e.getDate(),
            e.getHours(),
            e.getMinutes()
          );
        },
        fmt: function (e) {
          return (
            "" +
            t.pad2(e.getHours()) +
            ":" +
            t.pad2(e.getMinutes()) +
            ":" +
            t.pad2(e.getSeconds())
          );
        },
        incr: function (t) {
          return t.setUTCSeconds(t.getUTCSeconds() + e);
        },
      };
    }),
    (t.LABEL_SPECS = {
      decade: {
        span: 1728e8,
        start: function (e) {
          return new Date(e.getFullYear() - (e.getFullYear() % 10), 0, 1);
        },
        fmt: function (e) {
          return "" + e.getFullYear();
        },
        incr: function (e) {
          return e.setFullYear(e.getFullYear() + 10);
        },
      },
      year: {
        span: 1728e7,
        start: function (e) {
          return new Date(e.getFullYear(), 0, 1);
        },
        fmt: function (e) {
          return "" + e.getFullYear();
        },
        incr: function (e) {
          return e.setFullYear(e.getFullYear() + 1);
        },
      },
      month: {
        span: 24192e5,
        start: function (e) {
          return new Date(e.getFullYear(), e.getMonth(), 1);
        },
        fmt: function (e) {
          return "" + e.getFullYear() + "-" + t.pad2(e.getMonth() + 1);
        },
        incr: function (e) {
          return e.setMonth(e.getMonth() + 1);
        },
      },
      day: {
        span: 864e5,
        start: function (e) {
          return new Date(e.getFullYear(), e.getMonth(), e.getDate());
        },
        fmt: function (e) {
          return (
            "" +
            e.getFullYear() +
            "-" +
            t.pad2(e.getMonth() + 1) +
            "-" +
            t.pad2(e.getDate())
          );
        },
        incr: function (e) {
          return e.setDate(e.getDate() + 1);
        },
      },
      hour: n(60),
      "30min": n(30),
      "15min": n(15),
      "10min": n(10),
      "5min": n(5),
      minute: n(1),
      "30sec": r(30),
      "15sec": r(15),
      "10sec": r(10),
      "5sec": r(5),
      second: r(1),
    }),
    (t.AUTO_LABEL_ORDER = [
      "decade",
      "year",
      "month",
      "day",
      "hour",
      "30min",
      "15min",
      "10min",
      "5min",
      "minute",
      "30sec",
      "15sec",
      "10sec",
      "5sec",
      "second",
    ]),
    (t.Area = (function (n) {
      function i(n) {
        var s;
        if (!(this instanceof t.Area)) return new t.Area(n);
        (s = e.extend({}, r, n)),
          (this.cumulative = !s.behaveLikeLine),
          s.fillOpacity === "auto" &&
            (s.fillOpacity = s.behaveLikeLine ? 0.8 : 1),
          i.__super__.constructor.call(this, s);
      }
      var r;
      return (
        o(i, n),
        (r = { fillOpacity: "auto", behaveLikeLine: !1 }),
        (i.prototype.calcPoints = function () {
          var e, t, n, r, i, s, o;
          (s = this.data), (o = []);
          for (r = 0, i = s.length; r < i; r++)
            (e = s[r]),
              (e._x = this.transX(e.x)),
              (t = 0),
              (e._y = function () {
                var r, i, s, o;
                (s = e.y), (o = []);
                for (r = 0, i = s.length; r < i; r++)
                  (n = s[r]),
                    this.options.behaveLikeLine
                      ? o.push(this.transY(n))
                      : ((t += n || 0), o.push(this.transY(t)));
                return o;
              }.call(this)),
              o.push((e._ymax = Math.max.apply(Math, e._y)));
          return o;
        }),
        (i.prototype.drawSeries = function () {
          var e, t, n, r, i, s, o, u, a, f, l;
          (this.seriesPoints = []),
            this.options.behaveLikeLine
              ? (t = function () {
                  a = [];
                  for (
                    var e = 0, t = this.options.ykeys.length - 1;
                    0 <= t ? e <= t : e >= t;
                    0 <= t ? e++ : e--
                  )
                    a.push(e);
                  return a;
                }.apply(this))
              : (t = function () {
                  f = [];
                  for (
                    var e = (u = this.options.ykeys.length - 1);
                    u <= 0 ? e <= 0 : e >= 0;
                    u <= 0 ? e++ : e--
                  )
                    f.push(e);
                  return f;
                }.apply(this)),
            (l = []);
          for (i = 0, s = t.length; i < s; i++)
            (e = t[i]),
              this._drawFillFor(e),
              this._drawLineFor(e),
              l.push(this._drawPointFor(e));
          return l;
        }),
        (i.prototype._drawFillFor = function (e) {
          var t;
          t = this.paths[e];
          if (t !== null)
            return (
              (t +=
                "L" +
                this.transX(this.xmax) +
                "," +
                this.bottom +
                "L" +
                this.transX(this.xmin) +
                "," +
                this.bottom +
                "Z"),
              this.drawFilledPath(t, this.fillForSeries(e))
            );
        }),
        (i.prototype.fillForSeries = function (e) {
          var t;
          return (
            (t = Raphael.rgb2hsl(this.colorFor(this.data[e], e, "line"))),
            Raphael.hsl(
              t.h,
              this.options.behaveLikeLine ? t.s * 0.9 : t.s * 0.75,
              Math.min(
                0.98,
                this.options.behaveLikeLine ? t.l * 1.2 : t.l * 1.25
              )
            )
          );
        }),
        (i.prototype.drawFilledPath = function (e, t) {
          return this.raphael
            .path(e)
            .attr("fill", t)
            .attr("fill-opacity", this.options.fillOpacity)
            .attr("stroke-width", 0);
        }),
        i
      );
    })(t.Line)),
    (t.Bar = (function (n) {
      function r(n) {
        (this.onHoverOut = u(this.onHoverOut, this)),
          (this.onHoverMove = u(this.onHoverMove, this)),
          (this.onGridClick = u(this.onGridClick, this));
        if (!(this instanceof t.Bar)) return new t.Bar(n);
        r.__super__.constructor.call(this, e.extend({}, n, { parseTime: !1 }));
      }
      return (
        o(r, n),
        (r.prototype.init = function () {
          this.cumulative = this.options.stacked;
          if (this.options.hideHover !== "always")
            return (
              (this.hover = new t.Hover({ parent: this.el })),
              this.on("hovermove", this.onHoverMove),
              this.on("hoverout", this.onHoverOut),
              this.on("gridclick", this.onGridClick)
            );
        }),
        (r.prototype.defaults = {
          barSizeRatio: 0.75,
          barGap: 3,
          barColors: [
            "#0b62a4",
            "#7a92a3",
            "#4da74d",
            "#afd8f8",
            "#edc240",
            "#cb4b4b",
            "#9440ed",
          ],
          xLabelMargin: 50,
        }),
        (r.prototype.calc = function () {
          var e;
          this.calcBars();
          if (this.options.hideHover === !1)
            return (e = this.hover).update.apply(
              e,
              this.hoverContentForRow(this.data.length - 1)
            );
        }),
        (r.prototype.calcBars = function () {
          var e, t, n, r, i, s, o;
          (s = this.data), (o = []);
          for (e = r = 0, i = s.length; r < i; e = ++r)
            (t = s[e]),
              (t._x = this.left + (this.width * (e + 0.5)) / this.data.length),
              o.push(
                (t._y = function () {
                  var e, r, i, s;
                  (i = t.y), (s = []);
                  for (e = 0, r = i.length; e < r; e++)
                    (n = i[e]),
                      n != null ? s.push(this.transY(n)) : s.push(null);
                  return s;
                }.call(this))
              );
          return o;
        }),
        (r.prototype.draw = function () {
          return this.options.axes && this.drawXAxis(), this.drawSeries();
        }),
        (r.prototype.drawXAxis = function () {
          var e, t, n, r, i, s, o, u, a, f, l, c, h;
          (f = this.bottom + this.options.padding / 2),
            (o = null),
            (s = null),
            (h = []);
          for (
            e = l = 0, c = this.data.length;
            0 <= c ? l < c : l > c;
            e = 0 <= c ? ++l : --l
          )
            (u = this.data[this.data.length - 1 - e]),
              (t = this.drawXAxisLabel(u._x, f, u.label)),
              (a = t.getBBox()),
              t.transform("r" + -this.options.xLabelAngle),
              (n = t.getBBox()),
              t.transform("t0," + n.height / 2 + "..."),
              this.options.xLabelAngle !== 0 &&
                ((i =
                  -0.5 *
                  a.width *
                  Math.cos((this.options.xLabelAngle * Math.PI) / 180)),
                t.transform("t" + i + ",0...")),
              (o == null || o >= n.x + n.width || (s != null && s >= n.x)) &&
              n.x >= 0 &&
              n.x + n.width < this.el.width()
                ? (this.options.xLabelAngle !== 0 &&
                    ((r =
                      (1.25 * this.options.gridTextSize) /
                      Math.sin((this.options.xLabelAngle * Math.PI) / 180)),
                    (s = n.x - r)),
                  h.push((o = n.x - this.options.xLabelMargin)))
                : h.push(t.remove());
          return h;
        }),
        (r.prototype.drawSeries = function () {
          var e, t, n, r, i, s, o, u, a, f, l, c, h, p;
          return (
            (n = this.width / this.options.data.length),
            (u = this.options.stacked != null ? 1 : this.options.ykeys.length),
            (e =
              (n * this.options.barSizeRatio - this.options.barGap * (u - 1)) /
              u),
            (o = (n * (1 - this.options.barSizeRatio)) / 2),
            (p = this.ymin <= 0 && this.ymax >= 0 ? this.transY(0) : null),
            (this.bars = function () {
              var u, d, v, m;
              (v = this.data), (m = []);
              for (r = u = 0, d = v.length; u < d; r = ++u)
                (a = v[r]),
                  (i = 0),
                  m.push(
                    function () {
                      var u, d, v, m;
                      (v = a._y), (m = []);
                      for (f = u = 0, d = v.length; u < d; f = ++u)
                        (h = v[f]),
                          h !== null
                            ? (p
                                ? ((c = Math.min(h, p)), (t = Math.max(h, p)))
                                : ((c = h), (t = this.bottom)),
                              (s = this.left + r * n + o),
                              this.options.stacked ||
                                (s += f * (e + this.options.barGap)),
                              (l = t - c),
                              this.options.stacked && (c -= i),
                              this.drawBar(
                                s,
                                c,
                                e,
                                l,
                                this.colorFor(a, f, "bar")
                              ),
                              m.push((i += l)))
                            : m.push(null);
                      return m;
                    }.call(this)
                  );
              return m;
            }.call(this))
          );
        }),
        (r.prototype.colorFor = function (e, t, n) {
          var r, i;
          return typeof this.options.barColors == "function"
            ? ((r = { x: e.x, y: e.y[t], label: e.label }),
              (i = {
                index: t,
                key: this.options.ykeys[t],
                label: this.options.labels[t],
              }),
              this.options.barColors.call(this, r, i, n))
            : this.options.barColors[t % this.options.barColors.length];
        }),
        (r.prototype.hitTest = function (e, t) {
          return this.data.length === 0
            ? null
            : ((e = Math.max(Math.min(e, this.right), this.left)),
              Math.min(
                this.data.length - 1,
                Math.floor((e - this.left) / (this.width / this.data.length))
              ));
        }),
        (r.prototype.onGridClick = function (e, t) {
          var n;
          return (
            (n = this.hitTest(e, t)),
            this.fire("click", n, this.options.data[n], e, t)
          );
        }),
        (r.prototype.onHoverMove = function (e, t) {
          var n, r;
          return (
            (n = this.hitTest(e, t)),
            (r = this.hover).update.apply(r, this.hoverContentForRow(n))
          );
        }),
        (r.prototype.onHoverOut = function () {
          if (this.options.hideHover !== !1) return this.hover.hide();
        }),
        (r.prototype.hoverContentForRow = function (e) {
          var t, n, r, i, s, o, u, a;
          (r = this.data[e]),
            (t = "<div class='morris-hover-row-label'>" + r.label + "</div>"),
            (a = r.y);
          for (n = o = 0, u = a.length; o < u; n = ++o)
            (s = a[n]),
              (t +=
                "<div class='morris-hover-point' style='color: " +
                this.colorFor(r, n, "label") +
                "'>\n  " +
                this.options.labels[n] +
                ":\n  " +
                this.yLabelFormat(s) +
                "\n</div>");
          return (
            typeof this.options.hoverCallback == "function" &&
              (t = this.options.hoverCallback(e, this.options, t)),
            (i = this.left + ((e + 0.5) * this.width) / this.data.length),
            [t, i]
          );
        }),
        (r.prototype.drawXAxisLabel = function (e, t, n) {
          var r;
          return (r = this.raphael
            .text(e, t, n)
            .attr("font-size", this.options.gridTextSize)
            .attr("font-family", this.options.gridTextFamily)
            .attr("font-weight", this.options.gridTextWeight)
            .attr("fill", this.options.gridTextColor));
        }),
        (r.prototype.drawBar = function (e, t, n, r, i) {
          return this.raphael
            .rect(e, t, n, r)
            .attr("fill", i)
            .attr("stroke-width", 0);
        }),
        r
      );
    })(t.Grid)),
    (t.Donut = (function (n) {
      function r(n) {
        (this.select = u(this.select, this)),
          (this.click = u(this.click, this));
        var r;
        if (!(this instanceof t.Donut)) return new t.Donut(n);
        typeof n.element == "string"
          ? (this.el = e(document.getElementById(n.element)))
          : (this.el = e(n.element)),
          (this.options = e.extend({}, this.defaults, n));
        if (this.el === null || this.el.length === 0)
          throw new Error("Graph placeholder not found.");
        if (n.data === void 0 || n.data.length === 0) return;
        (this.data = n.data),
          (this.values = function () {
            var e, t, n, i;
            (n = this.data), (i = []);
            for (e = 0, t = n.length; e < t; e++)
              (r = n[e]), i.push(parseFloat(r.value));
            return i;
          }.call(this)),
          this.redraw();
      }
      return (
        o(r, n),
        (r.prototype.defaults = {
          colors: [
            "#0B62A4",
            "#3980B5",
            "#679DC6",
            "#95BBD7",
            "#B0CCE1",
            "#095791",
            "#095085",
            "#083E67",
            "#052C48",
            "#042135",
          ],
          backgroundColor: "#FFFFFF",
          labelColor: "#000000",
          formatter: t.commas,
        }),
        (r.prototype.redraw = function () {
          var e,
            n,
            r,
            i,
            s,
            o,
            u,
            a,
            f,
            l,
            c,
            h,
            p,
            d,
            v,
            m,
            g,
            y,
            b,
            w,
            E,
            S,
            x;
          this.el.empty(),
            (this.raphael = new Raphael(this.el[0])),
            (n = this.el.width() / 2),
            (r = this.el.height() / 2),
            (p = (Math.min(n, r) - 10) / 3),
            (c = 0),
            (w = this.values);
          for (d = 0, g = w.length; d < g; d++) (h = w[d]), (c += h);
          (a = 5 / (2 * p)),
            (e = 1.9999 * Math.PI - a * this.data.length),
            (o = 0),
            (s = 0),
            (this.segments = []),
            (E = this.values);
          for (i = v = 0, y = E.length; v < y; i = ++v)
            (h = E[i]),
              (f = o + a + e * (h / c)),
              (l = new t.DonutSegment(
                n,
                r,
                p * 2,
                p,
                o,
                f,
                this.options.colors[s % this.options.colors.length],
                this.options.backgroundColor,
                s,
                this.raphael
              )),
              l.render(),
              this.segments.push(l),
              l.on("hover", this.select),
              l.on("click", this.click),
              (o = f),
              (s += 1);
          (this.text1 = this.drawEmptyDonutLabel(
            n,
            r - 10,
            this.options.labelColor,
            15,
            800
          )),
            (this.text2 = this.drawEmptyDonutLabel(
              n,
              r + 10,
              this.options.labelColor,
              14
            )),
            (u = Math.max.apply(
              null,
              function () {
                var e, t, n, r;
                (n = this.values), (r = []);
                for (e = 0, t = n.length; e < t; e++) (h = n[e]), r.push(h);
                return r;
              }.call(this)
            )),
            (s = 0),
            (S = this.values),
            (x = []);
          for (m = 0, b = S.length; m < b; m++) {
            h = S[m];
            if (h === u) {
              this.select(s);
              break;
            }
            x.push((s += 1));
          }
          return x;
        }),
        (r.prototype.click = function (e) {
          return this.fire("click", e, this.data[e]);
        }),
        (r.prototype.select = function (e) {
          var t, n, r, i, s, o;
          o = this.segments;
          for (i = 0, s = o.length; i < s; i++) (n = o[i]), n.deselect();
          return (
            (r = this.segments[e]),
            r.select(),
            (t = this.data[e]),
            this.setLabels(t.label, this.options.formatter(t.value, t))
          );
        }),
        (r.prototype.setLabels = function (e, t) {
          var n, r, i, s, o, u, a, f;
          return (
            (n =
              ((Math.min(this.el.width() / 2, this.el.height() / 2) - 10) * 2) /
              3),
            (s = 1.8 * n),
            (i = n / 2),
            (r = n / 3),
            this.text1.attr({ text: e, transform: "" }),
            (o = this.text1.getBBox()),
            (u = Math.min(s / o.width, i / o.height)),
            this.text1.attr({
              transform:
                "S" +
                u +
                "," +
                u +
                "," +
                (o.x + o.width / 2) +
                "," +
                (o.y + o.height),
            }),
            this.text2.attr({ text: t, transform: "" }),
            (a = this.text2.getBBox()),
            (f = Math.min(s / a.width, r / a.height)),
            this.text2.attr({
              transform:
                "S" + f + "," + f + "," + (a.x + a.width / 2) + "," + a.y,
            })
          );
        }),
        (r.prototype.drawEmptyDonutLabel = function (e, t, n, r, i) {
          var s;
          return (
            (s = this.raphael
              .text(e, t, "")
              .attr("font-size", r)
              .attr("fill", n)),
            i != null && s.attr("font-weight", i),
            s
          );
        }),
        r
      );
    })(t.EventEmitter)),
    (t.DonutSegment = (function (e) {
      function t(e, t, n, r, i, s, o, a, f, l) {
        (this.cx = e),
          (this.cy = t),
          (this.inner = n),
          (this.outer = r),
          (this.color = o),
          (this.backgroundColor = a),
          (this.index = f),
          (this.raphael = l),
          (this.deselect = u(this.deselect, this)),
          (this.select = u(this.select, this)),
          (this.sin_p0 = Math.sin(i)),
          (this.cos_p0 = Math.cos(i)),
          (this.sin_p1 = Math.sin(s)),
          (this.cos_p1 = Math.cos(s)),
          (this.is_long = s - i > Math.PI ? 1 : 0),
          (this.path = this.calcSegment(
            this.inner + 3,
            this.inner + this.outer - 5
          )),
          (this.selectedPath = this.calcSegment(
            this.inner + 3,
            this.inner + this.outer
          )),
          (this.hilight = this.calcArc(this.inner));
      }
      return (
        o(t, e),
        (t.prototype.calcArcPoints = function (e) {
          return [
            this.cx + e * this.sin_p0,
            this.cy + e * this.cos_p0,
            this.cx + e * this.sin_p1,
            this.cy + e * this.cos_p1,
          ];
        }),
        (t.prototype.calcSegment = function (e, t) {
          var n, r, i, s, o, u, a, f, l, c;
          return (
            (l = this.calcArcPoints(e)),
            (n = l[0]),
            (i = l[1]),
            (r = l[2]),
            (s = l[3]),
            (c = this.calcArcPoints(t)),
            (o = c[0]),
            (a = c[1]),
            (u = c[2]),
            (f = c[3]),
            "M" +
              n +
              "," +
              i +
              ("A" + e + "," + e + ",0," + this.is_long + ",0," + r + "," + s) +
              ("L" + u + "," + f) +
              ("A" + t + "," + t + ",0," + this.is_long + ",1," + o + "," + a) +
              "Z"
          );
        }),
        (t.prototype.calcArc = function (e) {
          var t, n, r, i, s;
          return (
            (s = this.calcArcPoints(e)),
            (t = s[0]),
            (r = s[1]),
            (n = s[2]),
            (i = s[3]),
            "M" +
              t +
              "," +
              r +
              ("A" + e + "," + e + ",0," + this.is_long + ",0," + n + "," + i)
          );
        }),
        (t.prototype.render = function () {
          var e = this;
          return (
            (this.arc = this.drawDonutArc(this.hilight, this.color)),
            (this.seg = this.drawDonutSegment(
              this.path,
              this.color,
              this.backgroundColor,
              function () {
                return e.fire("hover", e.index);
              },
              function () {
                return e.fire("click", e.index);
              }
            ))
          );
        }),
        (t.prototype.drawDonutArc = function (e, t) {
          return this.raphael
            .path(e)
            .attr({ stroke: t, "stroke-width": 2, opacity: 0 });
        }),
        (t.prototype.drawDonutSegment = function (e, t, n, r, i) {
          return this.raphael
            .path(e)
            .attr({ fill: t, stroke: n, "stroke-width": 3 })
            .hover(r)
            .click(i);
        }),
        (t.prototype.select = function () {
          if (!this.selected)
            return (
              this.seg.animate({ path: this.selectedPath }, 150, "<>"),
              this.arc.animate({ opacity: 1 }, 150, "<>"),
              (this.selected = !0)
            );
        }),
        (t.prototype.deselect = function () {
          if (this.selected)
            return (
              this.seg.animate({ path: this.path }, 150, "<>"),
              this.arc.animate({ opacity: 0 }, 150, "<>"),
              (this.selected = !1)
            );
        }),
        t
      );
    })(t.EventEmitter));
}.call(this));
