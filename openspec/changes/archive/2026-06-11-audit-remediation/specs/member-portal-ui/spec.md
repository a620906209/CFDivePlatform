## MODIFIED Requirements

### Requirement: 專案基礎建設
前端 SHALL 建立於本 repo 的 `frontend/` 目錄（原規劃獨立 repo，後併入主 repo 以簡化版控與部署），使用 Vue 3 + Vite + Tailwind CSS + Vue Router 4 + Pinia + Axios，並設定 `.env` 指定後端 API base URL。

#### Scenario: 開發環境啟動
- **WHEN** 開發者執行 `npm run dev`
- **THEN** 應用在 `http://localhost:5173` 啟動，無編譯錯誤

#### Scenario: API base URL 設定
- **WHEN** `.env` 中設定 `VITE_API_URL=http://localhost:80`
- **THEN** 所有 Axios 請求以此為 base URL
