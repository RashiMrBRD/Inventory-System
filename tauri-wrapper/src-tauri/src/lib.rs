// Learn more about Tauri commands at https://tauri.app/develop/calling-rust/
use tauri::Manager;

#[tauri::command]
fn greet(name: &str) -> String {
    format!("Hello, {}! You've been greeted from Rust!", name)
}

fn is_allowed_host(host: &str) -> bool {
    let host = host.to_ascii_lowercase();

    host == "demo.rashlink.eu.org"
        || host == "live.rashlink.eu.org"
        || (cfg!(debug_assertions)
            && (host == "localhost" || host == "127.0.0.1" || host == "::1"))
}

fn is_allowed_navigation(url: &tauri::Url) -> bool {
    match url.scheme() {
        "http" | "https" => url.host_str().is_some_and(is_allowed_host),
        "about" => url.as_str() == "about:blank",
        _ => false,
    }
}

fn should_open_externally(url: &tauri::Url) -> bool {
    matches!(url.scheme(), "http" | "https" | "mailto" | "tel")
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .invoke_handler(tauri::generate_handler![greet])
        .setup(|app| {
            let app_handle = app.handle().clone();

            let main_window_config = app
                .config()
                .app
                .windows
                .iter()
                .find(|w| w.label == "main")
                .expect("main window config not found");

            let _main_window = tauri::WebviewWindowBuilder::from_config(app, main_window_config)?
                .on_navigation(move |url| {
                    if is_allowed_navigation(url) {
                        return true;
                    }

                    if should_open_externally(url) {
                        let _ = tauri_plugin_opener::open_url(url.as_str(), None::<&str>);
                    }

                    false
                })
                .on_new_window(move |url, _features| {
                    if is_allowed_navigation(&url) {
                        if let Some(main_window) = app_handle.get_webview_window("main") {
                            let _ = main_window.navigate(url);
                        }

                        return tauri::webview::NewWindowResponse::Deny;
                    }

                    if should_open_externally(&url) {
                        let _ = tauri_plugin_opener::open_url(url.as_str(), None::<&str>);
                    }

                    tauri::webview::NewWindowResponse::Deny
                })
                .build()?;

            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
