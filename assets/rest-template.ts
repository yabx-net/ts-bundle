export type THttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type THeaders = Record<string, string>;

export type TValidationError = {
  key: string;
  name: string;
  error: string;
}

export type TResponse = {
  result?: any
  error?: string;
  code?: number;
  validation?: TValidationError;
}

class RestAPI {
  private readonly url: string;
  private token: string | null = null;
  private statusCode: number = 0;
  private instances: Record<string, object> = {};
  private authErrorHandler?: () => void;
  private headersHandler?: (headers: THeaders) => void;
  public validation?: TValidationError;
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

  setHeadersHandler = (handler?: (headers: THeaders) => void) => {
    this.headersHandler = handler;
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

  get = <T>(endpoint: string, payload?: object | FormData, fields?: string[]): Promise<T> => {
    return this.request('GET', endpoint, payload, fields);
  };

  post = <T>(endpoint: string, payload?: object | FormData, fields?: string[]): Promise<T> => {
    return this.request('POST', endpoint, payload, fields);
  };

  put = <T>(endpoint: string, payload?: object | FormData, fields?: string[]): Promise<T> => {
    return this.request('PUT', endpoint, payload, fields);
  };

  patch = <T>(endpoint: string, payload?: object | FormData, fields?: string[]): Promise<T> => {
    return this.request('PATCH', endpoint, payload, fields);
  };

  delete = <T>(endpoint: string, payload?: object | FormData, fields?: string[]): Promise<T> => {
    return this.request('DELETE', endpoint, payload, fields);
  };

  private request = <T>(method: THttpMethod, endpoint: string, payload: object | FormData = {}, fields: string[] = []): Promise<T> => {
    // @ts-ignore
    return new Promise((resolve, reject) => {
      const processReject = (error: string, code: number, validation?: TValidationError) => {
        this.validation = validation;
        if (this.debug) console.error('Error', error, validation);
        if (code === 401 && this.authErrorHandler) this.authErrorHandler();
        else reject(error);
      };

      const options: { method: string; headers: THeaders; body?: FormData | string } = {
        method: method.toUpperCase(),
        headers: {
          accept: 'application/json',
        },
      };

      if (payload instanceof FormData) {
        payload.append('fields', fields.join(','))
        options.body = payload;
      } else {
        options.headers['content-type'] = 'application/json';
        // @ts-ignore
        payload['fields'] = fields;
        if (payload && method !== 'GET') options.body = JSON.stringify(payload);
      }

      if (this.token) {
        options.headers['authorization'] = 'Bearer ' + this.token;
      }

      this.statusCode = 0;
      this.validation = undefined;

      if (payload && method === 'GET') {
        endpoint += '?__payload=' + encodeURIComponent(JSON.stringify(payload));
      }

      if (this.debug) console.log('Request', method, endpoint.split('?')[0], JSON.parse(JSON.stringify(payload)));

      if(this.headersHandler) {
        this.headersHandler(options.headers);
      }

      fetch(this.url + endpoint, options)
      .then((response) => {
        this.statusCode = response.status;
        response
        .json()
        .then((data: TResponse) => {
          if (data.error) processReject(data.error, response.status, data.validation);
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

