package id.co.elmecon.deliverystock;
import android.app.Activity;
import android.os.*;
import android.security.keystore.*;
import android.webkit.*;
import android.view.WindowManager;
import android.view.View;
import android.view.WindowInsets;
import android.view.WindowInsetsController;
import android.widget.FrameLayout;
import android.graphics.Color;
import android.graphics.Insets;
import android.util.Base64;
import org.json.JSONObject;
import java.net.URL;
import javax.net.ssl.HttpsURLConnection;
import java.security.*;
import java.security.spec.ECGenParameterSpec;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.*;

public final class MainActivity extends Activity {
    private static final String ALIAS="delivery-stock-v2-key", PAGE="file:///android_asset/mobile.html";
    private final ExecutorService executor=Executors.newSingleThreadExecutor();
    private WebView web;
    @Override public void onCreate(Bundle b){
        super.onCreate(b);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_SECURE);
        // Keep the existing secure WebView inside a native safe-area container.
        // Android 15+ enforces edge-to-edge for targetSdk 35; handle insets explicitly.
        getWindow().setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE);
        final FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.WHITE);
        if (Build.VERSION.SDK_INT >= 30) {
            getWindow().setDecorFitsSystemWindows(false);
            getWindow().setStatusBarColor(Color.TRANSPARENT);
            getWindow().setNavigationBarColor(Color.TRANSPARENT);
            root.setOnApplyWindowInsetsListener((v, insets) -> {
                Insets safe = insets.getInsets(WindowInsets.Type.systemBars()
                    | WindowInsets.Type.displayCutout() | WindowInsets.Type.ime());
                // One padding owner only. Prevent a second inset in WebView.
                v.setPadding(safe.left, safe.top, safe.right, safe.bottom);
                return WindowInsets.CONSUMED;
            });
        } else {
            // API 24–29: normal platform decor fitting; no manual double padding.
            getWindow().setStatusBarColor(Color.WHITE);
            int flags = View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR;
            if (Build.VERSION.SDK_INT >= 26) {
                getWindow().setNavigationBarColor(Color.WHITE);
                flags |= View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR;
            } else {
                getWindow().setNavigationBarColor(Color.BLACK);
            }
            getWindow().getDecorView().setSystemUiVisibility(flags);
        }
        web=new WebView(this);
        WebSettings s=web.getSettings();s.setJavaScriptEnabled(true);s.setAllowFileAccess(false);s.setAllowContentAccess(false);s.setAllowFileAccessFromFileURLs(false);s.setAllowUniversalAccessFromFileURLs(false);s.setBlockNetworkLoads(true);s.setCacheMode(WebSettings.LOAD_NO_CACHE);
        web.setWebViewClient(new WebViewClient(){
            @Override public boolean shouldOverrideUrlLoading(WebView v,WebResourceRequest r){return true;}
            @Override public WebResourceResponse shouldInterceptRequest(WebView v,WebResourceRequest r){if(PAGE.equals(r.getUrl().toString()))return null;return new WebResourceResponse("text/plain","UTF-8",new ByteArrayInputStream(new byte[0]));}
        });
        web.addJavascriptInterface(new Bridge(),"Native");
        root.addView(web, new FrameLayout.LayoutParams(-1, -1));
        setContentView(root);
        if (Build.VERSION.SDK_INT >= 30) {
            WindowInsetsController controller = getWindow().getInsetsController();
            if (controller != null) {
                int light = WindowInsetsController.APPEARANCE_LIGHT_STATUS_BARS
                    | WindowInsetsController.APPEARANCE_LIGHT_NAVIGATION_BARS;
                controller.setSystemBarsAppearance(light, light);
            }
            root.post(root::requestApplyInsets);
        }
        web.loadUrl(PAGE);
    }
    private static String b64(byte[] b){return Base64.encodeToString(b,Base64.NO_WRAP);}
    private static String random(int n){byte[] b=new byte[n];new SecureRandom().nextBytes(b);return b64(b);}
    private KeyStore keys()throws Exception{
        KeyStore ks=KeyStore.getInstance("AndroidKeyStore");ks.load(null);
        if(!ks.containsAlias(ALIAS)){
            KeyPairGenerator g=KeyPairGenerator.getInstance("EC","AndroidKeyStore");
            g.initialize(new KeyGenParameterSpec.Builder(ALIAS,KeyProperties.PURPOSE_SIGN|KeyProperties.PURPOSE_VERIFY).setAlgorithmParameterSpec(new ECGenParameterSpec("secp256r1")).setDigests(KeyProperties.DIGEST_SHA256).build());g.generateKeyPair();
            if(!getPreferences(0).edit().remove("token").commit())throw new IOException();ks.load(null);
        }return ks;
    }
    private final class Bridge{
        @JavascriptInterface public void request(String raw){executor.execute(()->{
            JSONObject reply=new JSONObject();try{JSONObject req=new JSONObject(raw);reply.put("id",req.getInt("id"));reply.put("data",call(req.getString("action"),req.getJSONObject("data")));}catch(Exception e){try{reply.put("error","Gagal terhubung / konfigurasi server belum siap.");}catch(Exception ignored){}}
            runOnUiThread(()->{if(web!=null)web.evaluateJavascript("receive("+reply+")",null);});
        });}
    }
    private JSONObject call(String action,JSONObject data)throws Exception{
        if(!action.equals("status")&&!action.equals("register")&&!action.equals("stock")&&!action.equals("admin_list")&&!action.equals("admin_code")&&!action.equals("admin_approve"))throw new IOException();
        URL url=new URL(BuildConfig.DEVICE_API_URL);if(!"https".equals(url.getProtocol())||url.getUserInfo()!=null)throw new IOException();
        KeyStore ks=keys();String token=getPreferences(0).getString("token","");if(token.isEmpty()){token=random(32);if(!getPreferences(0).edit().putString("token",token).commit())throw new IOException();}
        JSONObject p=new JSONObject().put("action",action).put("token",token).put("public_key",b64(ks.getCertificate(ALIAS).getPublicKey().getEncoded())).put("timestamp",System.currentTimeMillis()/1000).put("nonce",random(16)).put("platform","android").put("model",Build.MANUFACTURER+" "+Build.MODEL).put("version",204).put("data",data);
        String encoded=b64(p.toString().getBytes(StandardCharsets.UTF_8));Signature sign=Signature.getInstance("SHA256withECDSA");sign.initSign((PrivateKey)ks.getKey(ALIAS,null));sign.update(encoded.getBytes(StandardCharsets.US_ASCII));
        byte[] body=new JSONObject().put("payload",encoded).put("signature",b64(sign.sign())).toString().getBytes(StandardCharsets.UTF_8);
        HttpsURLConnection c=(HttpsURLConnection)url.openConnection();c.setInstanceFollowRedirects(false);c.setConnectTimeout(10000);c.setReadTimeout(15000);c.setRequestMethod("POST");c.setDoOutput(true);c.setRequestProperty("Content-Type","application/json");
        try{try(OutputStream out=c.getOutputStream()){out.write(body);}if(c.getResponseCode()!=200)throw new IOException();try(InputStream in=c.getInputStream();ByteArrayOutputStream out=new ByteArrayOutputStream()){byte[] buf=new byte[4096];int n;while((n=in.read(buf))!=-1){out.write(buf,0,n);if(out.size()>1000000)throw new IOException();}return new JSONObject(out.toString("UTF-8"));}}finally{c.disconnect();}
    }
    @Override protected void onDestroy(){executor.shutdownNow();if(web!=null){web.removeJavascriptInterface("Native");web.destroy();web=null;}super.onDestroy();}
}
