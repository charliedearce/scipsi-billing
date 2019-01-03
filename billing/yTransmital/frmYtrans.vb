Public Class frmYtrans
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub btnAddBill_Click(sender As Object, e As EventArgs) Handles btnAddBill.Click
        If txtYtransno.Text = "" Then
            txtNotice.Text = "Please fill up the important fields."
        Else
            frmTransmitBillno.ShowDialog()
        End If
    End Sub

    Private Sub btnNewTrans_Click(sender As Object, e As EventArgs) Handles btnNewTrans.Click
        btnDelTrans.Enabled = False
        btnAddBill.Enabled = True
        btnNewTrans.Enabled = False
        btnEdit.Enabled = False
        txtYtransno.Enabled = True
        txtBilldate.Enabled = True
        txtYtransno.Text = ""
        txtBilldate.Text = ""
        txtindi.Text = 1
        txtindi1.Text = 0
        ViewYTrans()
        txtYtransno.Focus()
        txtNotice.Text = ""
    End Sub

    Private Sub btnDelBill_Click(sender As Object, e As EventArgs) Handles btnDelBill.Click
        frmTransDelBill.ShowDialog()
    End Sub

    Private Sub btnPrintTrans_Click(sender As Object, e As EventArgs) Handles btnPrintTrans.Click
        If txtYtransno.Text = "" Then
            txtNotice.Text = "Please fill up the important fields."
        Else
            If txtindi1.Text = 0 Then
                SaveYmital()
                frmPrintYtrans.ShowDialog()
                txtYtransno.Enabled = False
                txtBilldate.Enabled = False
                btnAddBill.Enabled = False
                btnDelBill.Enabled = False
                btnDelTrans.Enabled = False
                btnSearchTrans.Enabled = True
                btnNewTrans.Enabled = True
                btnClose.Enabled = True

            Else
                UpdateYmital()
                'frmPrintStatement.ShowDialog()
                txtYtransno.Enabled = False
                txtBilldate.Enabled = False
                btnAddBill.Enabled = False
                btnDelBill.Enabled = False
                btnDelTrans.Enabled = False
                btnSearchTrans.Enabled = True
                btnNewTrans.Enabled = True
                btnClose.Enabled = True
                btnEdit.Enabled = True
                frmPrintYtrans.ShowDialog()
            End If
        End If

    End Sub

    Private Sub btnEdit_Click(sender As Object, e As EventArgs) Handles btnEdit.Click
        txtBilldate.Enabled = True
        btnAddBill.Enabled = True
        btnDelBill.Enabled = True
        btnDelTrans.Enabled = False
        btnNewTrans.Enabled = False
        btnEdit.Enabled = False
        txtindi.Text = 1
        txtindi1.Text = 1
    End Sub

    Private Sub btnSearchTrans_Click(sender As Object, e As EventArgs) Handles btnSearchTrans.Click
        txtindi.Text = 0
        frmSearchYmital.ShowDialog()
    End Sub

    Private Sub btnClose_Click(sender As Object, e As EventArgs) Handles btnClose.Click
        Me.Dispose()
    End Sub

    Private Sub dgYtransmittal_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgYtransmittal.CellContentClick
       
    End Sub

    Private Sub dgYtransmittal_CellDoubleClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgYtransmittal.CellDoubleClick
        If txtindi.Text = 0 Then
        Else
            frmYtransVoyage.lblbillno.Text = dgYtransmittal.CurrentRow.Cells(1).Value.ToString
            frmYtransVoyage.txtVoyage.Text = dgYtransmittal.CurrentRow.Cells(5).Value.ToString


            frmYtransVoyage.ShowDialog()
        End If
    End Sub

    Private Sub btnDelTrans_Click(sender As Object, e As EventArgs) Handles btnDelTrans.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to delete this Transmittal #" + txtYtransno.Text + " ?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            YtransDel()
            ViewYTrans()
        End If
    End Sub

    Private Sub frmYtrans_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.F1) Then
            If btnNewTrans.Enabled = True Then
                btnNewTrans_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F2) Then
            If btnAddBill.Enabled = True Then
                btnAddBill_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F3) Then
            If btnDelBill.Enabled = True Then
                btnDelBill_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F4) Then
            If btnPrintTrans.Enabled = True Then
                btnPrintTrans_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F5) Then
            If btnDelTrans.Enabled = True Then
                btnDelTrans_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F6) Then
            If btnSearchTrans.Enabled = True Then
                btnSearchTrans_Click(sender, e)
            End If
        ElseIf (e.KeyCode = Keys.F7) Then
            If btnEdit.Enabled = True Then
                btnEdit_Click(sender, e)
            End If
        End If
    End Sub

    Private Sub frmYtrans_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub
End Class