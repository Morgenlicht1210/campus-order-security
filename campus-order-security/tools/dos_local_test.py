import argparse
import statistics
import threading
import time
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib.parse import parse_qsl, urlencode, urlparse, urlunparse


ALLOWED_HOSTS = {"localhost", "127.0.0.1", "::1"}
MAX_MANUAL_SECONDS = 300


def status_label(status: int | str) -> str:
    if status == 429:
        return "429 Forbidden"
    if status == 200:
        return "200 OK"
    return str(status)


def with_query(url: str, **params: str) -> str:
    parsed = urlparse(url)
    query = dict(parse_qsl(parsed.query, keep_blank_values=True))
    query.update(params)
    return urlunparse(parsed._replace(query=urlencode(query)))


def check_local_target(url: str) -> None:
    parsed = urlparse(url)
    host = parsed.hostname
    if host not in ALLOWED_HOSTS:
        raise SystemExit(
            "安全限制：本脚本只允许测试 localhost / 127.0.0.1 / ::1 的本地授权环境。"
        )


def fetch(url: str, timeout: float) -> dict:
    start = time.perf_counter()
    try:
        request = urllib.request.Request(
            url,
            headers={
                "User-Agent": "local-dos-course-test/1.0",
            },
            method="GET",
        )
        with urllib.request.urlopen(request, timeout=timeout) as response:
            response.read(1024)
            elapsed = time.perf_counter() - start
            return {
                "ok": True,
                "status": response.status,
                "elapsed": elapsed,
                "error": "",
            }
    except urllib.error.HTTPError as exc:
        elapsed = time.perf_counter() - start
        return {
            "ok": True,
            "status": exc.code,
            "elapsed": elapsed,
            "error": "",
        }
    except Exception as exc:
        elapsed = time.perf_counter() - start
        return {
            "ok": False,
            "status": "ERROR",
            "elapsed": elapsed,
            "error": type(exc).__name__,
        }


def run_test(url: str, total: int, concurrency: int, timeout: float, work_rounds: int) -> None:
    check_local_target(url)
    traffic_url = with_query(url, api="traffic", work_rounds=str(work_rounds))

    print("DoS 本地低强度模拟测试")
    print(f"页面地址: {url}")
    print(f"脚本请求接口: {traffic_url}")
    print(f"请求总数: {total}")
    print(f"并发数量: {concurrency}")
    print(f"请求超时: {timeout}s")
    print("-" * 50)

    started = time.perf_counter()
    results = []

    with ThreadPoolExecutor(max_workers=concurrency) as pool:
        futures = [pool.submit(fetch, traffic_url, timeout) for _ in range(total)]
        for future in as_completed(futures):
            result = future.result()
            results.append(result)
            print(
                f"status={status_label(result['status'])} "
                f"time={result['elapsed']:.3f}s "
                f"error={result['error']}"
            )

    total_elapsed = time.perf_counter() - started
    completed = sum(1 for r in results if r["ok"])
    failed = total - completed
    allowed = sum(1 for r in results if r["status"] == 200)
    blocked = sum(1 for r in results if r["status"] == 429)
    times = [r["elapsed"] for r in results]

    status_count = {}
    for result in results:
        status_count[result["status"]] = status_count.get(result["status"], 0) + 1

    print("=" * 50)
    print("测试结果汇总")
    print(f"总耗时: {total_elapsed:.3f}s")
    print(f"完成请求: {completed}")
    print(f"网络失败: {failed}")
    print(f"放行 200: {allowed}")
    print(f"拦截 429: {blocked}")
    print(f"状态码分布: {status_count}")
    print(f"平均响应时间: {statistics.mean(times):.3f}s")
    print(f"最大响应时间: {max(times):.3f}s")
    print(f"最小响应时间: {min(times):.3f}s")

    print("\n报告写法建议：")
    print("本脚本仅在本地授权环境中进行低强度请求模拟，用于观察高频请求对响应时间的影响。")
    print("若系统实现限流，超过阈值后应返回 429，并可写入 request_limits 与 security_events。")


def print_summary(results: list[dict], started: float) -> None:
    total_elapsed = time.perf_counter() - started
    completed = sum(1 for r in results if r["ok"])
    failed = len(results) - completed
    allowed = sum(1 for r in results if r["status"] == 200)
    blocked = sum(1 for r in results if r["status"] == 429)
    times = [r["elapsed"] for r in results]

    status_count = {}
    for result in results:
        status_count[result["status"]] = status_count.get(result["status"], 0) + 1

    print("=" * 50)
    print("测试结果汇总")
    print(f"总耗时: {total_elapsed:.3f}s")
    print(f"总请求数: {len(results)}")
    print(f"完成请求: {completed}")
    print(f"网络失败: {failed}")
    print(f"放行 200: {allowed}")
    print(f"拦截 429: {blocked}")
    print(f"状态码分布: {status_count}")
    if times:
        print(f"平均响应时间: {statistics.mean(times):.3f}s")
        print(f"最大响应时间: {max(times):.3f}s")
        print(f"最小响应时间: {min(times):.3f}s")
    else:
        print("平均响应时间: 无请求数据")


def manual_worker(
    worker_id: int,
    url: str,
    timeout: float,
    interval: float,
    stop_event: threading.Event,
    results: list[dict],
    lock: threading.Lock,
) -> None:
    while not stop_event.is_set():
        result = fetch(url, timeout)
        with lock:
            results.append(result)
            print(
                f"worker={worker_id} "
                f"status={status_label(result['status'])} "
                f"time={result['elapsed']:.3f}s "
                f"error={result['error']}"
            )
        stop_event.wait(interval)


def run_manual(url: str, concurrency: int, timeout: float, interval: float, work_rounds: int) -> None:
    check_local_target(url)

    print("DoS 本地低强度模拟测试（手动开关模式）")
    traffic_url = with_query(url, api="traffic", work_rounds=str(work_rounds))
    print(f"页面地址: {url}")
    print(f"脚本请求接口: {traffic_url}")
    print(f"并发数量: {concurrency}")
    print(f"请求超时: {timeout}s")
    print(f"请求间隔: {interval}s")
    print(f"安全上限: 单次启动最多运行 {MAX_MANUAL_SECONDS}s")
    print("-" * 50)
    print("命令：start 开始发送请求；stop 停止并汇总；quit 退出")

    running = False
    stop_event = threading.Event()
    threads: list[threading.Thread] = []
    results: list[dict] = []
    lock = threading.Lock()
    started = 0.0
    active_url = traffic_url

    while True:
        try:
            command = input("dos> ").strip().lower()
        except KeyboardInterrupt:
            if running:
                stop_event.set()
                for thread in threads:
                    thread.join()
                running = False
                print("\n已收到 Ctrl+C，测试已停止。")
                print_summary(results, started)
            else:
                print("\n已退出。")
            return

        if command in {"quit", "exit", "q"}:
            if running:
                stop_event.set()
                for thread in threads:
                    thread.join()
                print_summary(results, started)
            print("已退出。")
            return

        if command in {"start", "s"}:
            if running:
                print("当前已经在运行，先输入 stop 停止。")
                continue
            check_local_target(active_url)
            running = True
            stop_event = threading.Event()
            threads = []
            results = []
            started = time.perf_counter()
            for worker_id in range(1, concurrency + 1):
                thread = threading.Thread(
                    target=manual_worker,
                    args=(worker_id, active_url, timeout, interval, stop_event, results, lock),
                    daemon=True,
                )
                threads.append(thread)
                thread.start()
            print("已开始发送请求。防御开关请在 dos_demo.php 网页端控制。输入 stop 停止并输出统计。")
            continue

        if command in {"stop", "x"}:
            if not running:
                print("当前没有运行中的测试。")
                continue
            stop_event.set()
            for thread in threads:
                thread.join()
            running = False
            print(f"请求接口: {active_url}")
            print_summary(results, started)
            print("\n报告写法建议：")
            print("脚本只负责发请求；是否成功或被拦截由 dos_demo.php 网页端防御开关决定。")
            print("刷新网页后查看 security_events 和 request_limits，即可看到 success 和 429 blocked 记录。")
            continue

        if running and time.perf_counter() - started > MAX_MANUAL_SECONDS:
            stop_event.set()
            for thread in threads:
                thread.join()
            running = False
            print("已达到安全运行上限，自动停止。")
            print_summary(results, started)
            continue

        print("未知命令。可用命令：start、stop、quit")


def main() -> None:
    parser = argparse.ArgumentParser(description="本地授权 DoS 低强度模拟测试脚本")
    parser.add_argument(
        "--url",
        default="http://localhost/dos_demo.php",
        help="只允许 localhost / 127.0.0.1 / ::1",
    )
    parser.add_argument("--total", type=int, default=50, help="请求总数")
    parser.add_argument("--concurrency", type=int, default=5, help="并发数量")
    parser.add_argument("--timeout", type=float, default=3.0, help="单请求超时时间")
    parser.add_argument("--interval", type=float, default=0.2, help="手动模式下每个线程两次请求之间的间隔")
    parser.add_argument("--work-rounds", type=int, default=45000, help="网页端单次请求的模拟计算量")
    parser.add_argument("--manual", action="store_true", help="启用手动开关模式：start 开始发送请求，stop 停止，quit 退出；防御开关在网页端控制")

    args = parser.parse_args()

    if args.total <= 0 or args.total > 500:
        raise SystemExit("安全限制：total 必须在 1 到 500 之间。")
    if args.concurrency <= 0 or args.concurrency > 20:
        raise SystemExit("安全限制：concurrency 必须在 1 到 20 之间。")
    if args.interval < 0.05 or args.interval > 10:
        raise SystemExit("安全限制：interval 必须在 0.05 到 10 秒之间。")
    if args.work_rounds < 1000 or args.work_rounds > 200000:
        raise SystemExit("安全限制：work-rounds 必须在 1000 到 200000 之间。")

    if args.manual:
        run_manual(args.url, args.concurrency, args.timeout, args.interval, args.work_rounds)
    else:
        run_test(args.url, args.total, args.concurrency, args.timeout, args.work_rounds)


if __name__ == "__main__":
    main()
