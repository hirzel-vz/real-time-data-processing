FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY json2h5p_question_set.py json2h5p_branching.py h5p_generation_service.py ./

EXPOSE 8000

CMD ["uvicorn", "h5p_generation_service:app", "--host", "0.0.0.0", "--port", "8000"]
