type THttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

class RestAPI {
  private readonly url: string;
  private token: string | null = null;
  private statusCode: number = 0;
  private instances: Record<string, object> = {};
  private authErrorHandler?: () => void;
  public debug: boolean = false;

  constructor(url: string, debug: boolean) {
    this.url = url;
    this.debug = debug;
  }

  public getUrl = (): string => {
    return this.url;
  };

  setAuthErrorHandler = (handler?: () => void) => {
    this.authErrorHandler = handler;
  }

  setToken = (token: string | null): this => {
    this.token = token;
    return this;
  };

  getToken = (): string | null => {
    return this.token;
  };

  getStatusCode = (): number => {
    return this.statusCode;
  };

  get = (endpoint: string, payload?: object | FormData, fields?: string[]): Promise<any> => {
    return this.request('GET', endpoint, payload, fields);
  };

  post = (endpoint: string, payload?: object | FormData, fields?: string[]): Promise<any> => {
    return this.request('POST', endpoint, payload, fields);
  };

  put = (endpoint: string, payload?: object | FormData, fields?: string[]): Promise<any> => {
    return this.request('PUT', endpoint, payload, fields);
  };

  patch = (endpoint: string, payload?: object | FormData, fields?: string[]): Promise<any> => {
    return this.request('PATCH', endpoint, payload, fields);
  };

  delete = (endpoint: string, payload?: object | FormData, fields?: string[]): Promise<any> => {
    return this.request('DELETE', endpoint, payload, fields);
  };

  private request = (method: THttpMethod, endpoint: string, payload: object | FormData = {}, fields: string[] = []): Promise<unknown> => {
    // @ts-ignore
    return new Promise((resolve, reject) => {
      const processReject = (error: string, code: number) => {
        if (this.debug) console.error('Error', error);
        if (code === 401 && this.authErrorHandler) this.authErrorHandler();
        else reject(error);
      };

      const options: { method: string; headers: Record<string, string>; body?: FormData | string } = {
        method: method.toUpperCase(),
        headers: {
          accept: 'application/json',
        },
      };

      if (payload instanceof FormData) {
        payload.append('__fields', fields.join(','))
        options.body = payload;
      } else {
        options.headers['content-type'] = 'application/json';
        // @ts-ignore
        payload['__fields'] = fields;
        if (payload && method !== 'GET') options.body = JSON.stringify(payload);
      }

      if (this.token) {
        options.headers['authorization'] = 'Bearer ' + this.token;
      }

      this.statusCode = 0;

      if (payload && method === 'GET') {
        endpoint += '?__payload=' + encodeURIComponent(JSON.stringify(payload));
      }

      if (this.debug) console.log('Request', method, endpoint.split('?')[0], JSON.parse(JSON.stringify(payload)));

      fetch(this.url + endpoint, options)
      .then((response) => {
        this.statusCode = response.status;
        response
        .json()
        .then((data) => {
          if (data.error) processReject(data.error, response.status);
          else {
            if (this.debug) console.info('Result', data.result);
            resolve(data.result);
          }
        })
        .catch((e) => processReject(e, -2));
      })
      .catch((e) => processReject(e, -1));
    });
  };

  //INCLUDE

}

export { RestAPI };

