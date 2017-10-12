import Promise from 'bluebird';

class Loader {
    loading = false;
    loaded_js = [];
    to_load_js = [];
    to_execute_js = [];
    to_load_css = [];
    loaded_css = [];

    static loadScript = file => new Promise(((resolve, reject) => {
      let script = document.createElement('script');
      script.type = 'text/javascript';
      script.onerror = reject;
      script.src = file;
      script.onload = script.onreadystatechange = function (_, isAbort) {
        if (isAbort || !script.readyState || /loaded|complete/.test(script.readyState)) {
          script.onload = script.onreadystatechange = null;
          script = undefined;

          if (!isAbort) {
            resolve();
          }
        }
      };
      document.getElementsByTagName('head').item(0).appendChild(script);
    }));

    static loadCss = file => new Promise(((resolve, reject) => {
      let link = document.createElement('link');
      link.type = 'text/css';
      link.rel = 'stylesheet';
      link.onerror = reject;
      link.href = file;
      link.onload = link.onreadystatechange = function (_, isAbort) {
        if (isAbort || !link.readyState || /loaded|complete/.test(link.readyState)) {
          link.onload = link.onreadystatechange = null;
          link = undefined;

          if (!isAbort) {
            resolve();
          }
        }
      };
      document.getElementsByTagName('head').item(0).appendChild(link);
    }));

    static insertScript = code => new Promise(((resolve, reject) => {
      const script = document.createElement('script');
      script.type = 'text/javascript';
      script.onerror = reject;
      script.text = code;
      document.getElementsByTagName('head').item(0).appendChild(script);
    }));

    load_js = async (file) => {
      if (this.loaded_js.includes(file) || this.to_load_js.includes(file)) return;
      this.to_load_js.push(file);
      await this.load();
    };

    load_css = async (file) => {
      if (this.loaded_css.includes(file) || this.to_load_css.includes(file)) return;
      this.to_load_css.push(file);
      await this.load();
    };

    execute_js = async (code) => {
      this.to_execute_js.push(code);
      await this.load();
    };

    load = async () => {
      if (this.loading === true) return;
      this.loading = true;

      let file;
      while (file = this.to_load_js.shift()) {
        if (this.loaded_js.includes(file)) continue;
        await Loader.loadScript(file);
        this.loaded_js.push(file);
      }

      let code;
      while (code = this.to_execute_js.shift()) {
        Loader.insertScript(code);
      }

      let css;
      while (css = this.to_load_css.shift()) {
        Loader.loadCss(css);
        this.loaded_css.push(css);
      }

      this.loading = false;
    }
}

export default Loader;
