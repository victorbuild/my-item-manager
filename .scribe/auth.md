# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {token}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

要進行認證，請先使用 `/login` endpoint 登入以取得 token。然後在請求的 Authorization header 中加入 `Bearer {token}`。
